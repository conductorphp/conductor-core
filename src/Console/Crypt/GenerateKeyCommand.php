<?php

declare(strict_types=1);

namespace ConductorCore\Console\Crypt;

use Rmg\Lib\Crypt\Api\EncryptionKeyGeneratorInterface;
use Rmg\Lib\Crypt\Sodium\EncryptionKeyGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints one new sodium key from `rmg/lib-crypt-sodium`'s generator, the same one the middleware's
 * `crypt:keys:generate` uses: the value `CONDUCTOR_CRYPT_KEY` takes (CTAP-1968). Nothing else goes
 * to stdout. Rotating rather than starting out? Move the key that is there now into
 * `CONDUCTOR_CRYPT_KEYS_PREVIOUS` before replacing it, re-encrypt, then drop it.
 */
class GenerateKeyCommand extends Command
{
    public function __construct(
        private readonly EncryptionKeyGeneratorInterface $keyGenerator = new EncryptionKeyGenerator(),
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('crypt:generate-key')
            ->setDescription('Generate a key (base64 of 32 bytes) for CONDUCTOR_CRYPT_KEY.')
            ->setHelp(
                "Prints one new sodium key for encrypting configuration values; export it as CONDUCTOR_CRYPT_KEY.\n"
                . "To rotate: move the current key into CONDUCTOR_CRYPT_KEYS_PREVIOUS, set the new one, re-encrypt\n"
                . "every value with crypt:encrypt, then drop the retired key."
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->keyGenerator->generate());

        return self::SUCCESS;
    }
}
