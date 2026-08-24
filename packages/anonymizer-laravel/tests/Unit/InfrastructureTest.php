<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerLaravel\Tests\Unit;

use InvalidArgumentException;
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerInterface;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AuditAnonymizationResult;
use PhpAnonymizer\Anonymizer\Application\Anonymization\Result\AuditEntry;
use PhpAnonymizer\Anonymizer\Application\Extension\Profile\ProfileCollection;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileCompositionCompiler;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinitionData;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinitionParser;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileMetadataParser;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileRuleAppender;
use PhpAnonymizer\Anonymizer\Domain\Rule\Compiler\RuleOptionsCompiler;
use PhpAnonymizer\Anonymizer\Domain\Rule\RuleDefinition;
use PhpAnonymizer\AnonymizerLaravel\Infrastructure\Audit\AuditLoggerAdapter;
use PhpAnonymizer\AnonymizerLaravel\Infrastructure\Payload\YamlPayloadCodec;
use PhpAnonymizer\AnonymizerLaravel\Infrastructure\Profile\ProfileComposer;
use PhpAnonymizer\AnonymizerLaravel\Tests\Support\Profile\TestProfileProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\Yaml\Exception\ParseException;

#[CoversClass(ProfileComposer::class)]
#[CoversClass(YamlPayloadCodec::class)]
#[CoversClass(AuditLoggerAdapter::class)]
#[UsesClass(ProfileCollection::class)]
#[UsesClass(ProfileDefinition::class)]
#[UsesClass(AuditAnonymizationResult::class)]
#[UsesClass(AuditEntry::class)]
#[UsesClass(RuleOptionsCompiler::class)]
#[UsesClass(RuleDefinition::class)]
#[UsesClass(ProfileDefinitionParser::class)]
#[UsesClass(ProfileMetadataParser::class)]
#[UsesClass(ProfileCompositionCompiler::class)]
#[UsesClass(ProfileDefinitionData::class)]
#[UsesClass(ProfileRuleAppender::class)]
final class InfrastructureTest extends TestCase
{
    public function testProfileComposerCombinesConfiguredAndTaggedDefinitions(): void
    {
        $provider = new TestProfileProvider();

        $collection = new ProfileComposer()->compose([
            'configured' => ['mode' => 'redact', 'rules' => [['field' => 'email']]],
        ], [$provider]);

        self::assertSame(['configured', 'tagged'], array_values(array_map(static fn (ProfileDefinition $profile): string => $profile->name(), iterator_to_array($collection->profiles()))));
    }

    public function testYamlCodecSupportsPayloadAndDocumentRoots(): void
    {
        $codec = new YamlPayloadCodec();

        self::assertSame(['email' => 'secret'], $codec->decode("email: secret\n"));
        self::assertSame(['first' => 'one', 'second' => 'two'], $codec->decode("first: one\nsecond: two\n"));
        self::assertSame(['email' => 'secret'], $codec->decodeDocument("email: secret\n"));
        self::assertSame(['one', 'two'], $codec->decodeDocument("- one\n- two\n"));
        self::assertSame("email: secret\n", $codec->encode(['email' => 'secret']));
        self::assertSame("- secret\n", $codec->encodeDocument(['secret']));
        self::assertSame('yaml', $codec->id());
        self::assertSame("root:\n  nested:\n    - one\n", $codec->encode(['root' => ['nested' => ['one']]]));
        self::assertSame("-\n  root:\n    nested:\n      - one\n", $codec->encodeDocument([['root' => ['nested' => ['one']]]]));
    }

    public function testYamlCodecRejectsInvalidRootsAndKeys(): void
    {
        $codec = new YamlPayloadCodec();

        $this->expectException(ParseException::class);
        $codec->decode("- secret\n");
    }

    public function testYamlCodecRejectsScalarDocumentRoots(): void
    {
        $this->expectException(ParseException::class);
        new YamlPayloadCodec()->decodeDocument("secret\n");
    }

    public function testAuditAdapterLogsEntries(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('Anonymization audit', [
            'profile' => 'p',
            'entries' => [[
                'fingerprint' => 'fingerprint',
                'path' => '/email',
                'action' => 'redact',
                'handler' => null,
                'count' => 1,
            ]],
        ]);
        $anonymizer = $this->createMock(AnonymizerInterface::class);
        $result = new AuditAnonymizationResult(['email' => '[REDACTED]'], [new AuditEntry('p', 'fingerprint', '/email', 'redact', null, 1)]);
        $anonymizer->expects($this->once())->method('anonymizeWithAudit')->willReturn($result);

        self::assertSame($result, new AuditLoggerAdapter($anonymizer, $logger)->anonymizeWithAudit('p', ['email' => 'secret']));
    }

    public function testProfileComposerRejectsInvalidConfiguredProfiles(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ProfileComposer()->compose(['invalid' => 'profile'], []);
    }

    public function testProfileComposerRejectsInvalidProviders(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ProfileComposer()->compose([], [new stdClass()]);
    }
}
