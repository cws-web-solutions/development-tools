<?php declare(strict_types=1);

namespace Cws\DevelopmentTools\Command;

use Cws\DevelopmentTools\Exception\DevelopmentToolsUnavailableException;
use Cws\DevelopmentTools\Service\DevelopmentMaintenanceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'cws:development-tools:refresh',
    description: 'Clears caches, compiles themes, and resets OPcache for development environments.'
)]
final class RefreshDevelopmentEnvironmentCommand extends Command
{
    public function __construct(
        private readonly DevelopmentMaintenanceService $developmentMaintenanceService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('active-only', 'a', InputOption::VALUE_NONE, 'Compile themes only for active sales channels')
            ->addOption('keep-assets', 'k', InputOption::VALUE_NONE, 'Keep current theme assets during compilation')
            ->addOption('skip-opcache-reset', null, InputOption::VALUE_NONE, 'Skip the OPcache reset step')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = $this->developmentMaintenanceService->refresh(
                (bool) $input->getOption('active-only'),
                (bool) $input->getOption('keep-assets'),
                !(bool) $input->getOption('skip-opcache-reset')
            );
        } catch (DevelopmentToolsUnavailableException $exception) {
            $io->error($exception->getMessage());

            return self::FAILURE;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return self::FAILURE;
        }

        $io->success('Development maintenance completed.');
        $io->definitionList(
            ['Environment' => $result['environment']],
            ['Compiled sales channels' => (string) count($result['compiledSalesChannelIds'])],
            ['Compiled themes' => (string) count($result['compiledThemeIds'])],
            ['OPcache' => $result['opcache']['message']]
        );

        if ($result['compiledSalesChannelIds'] !== []) {
            $io->writeln('Compiled sales channel IDs:');
            $io->listing($result['compiledSalesChannelIds']);
        } else {
            $io->note('No assigned storefront themes were found to compile.');
        }

        if ($result['opcache']['status'] === 'disabled' && PHP_SAPI === 'cli') {
            $io->note('If PHP-FPM OPcache is enabled, trigger the admin API action to reset the web runtime OPcache too.');
        }

        return self::SUCCESS;
    }
}
