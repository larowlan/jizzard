<?php

declare(strict_types=1);

namespace Larowlan\Jizzard\Commands;

use JiraRestApi\Configuration\ArrayConfiguration;
use JiraRestApi\IssueLink\IssueLinkService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Defines a command for getting link types.
 */
class LinkTypes extends Command {

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setName('info:link-types')
      ->setAliases(['ilt'])
      ->addArgument('project', InputArgument::REQUIRED, 'Project ID')
      ->setDescription('Link types info')
      ->setHelp('Show link types information')
      ->addUsage('TEST-PROJECT');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $root = dirname(__DIR__, 2);
    $configuration = Yaml::parseFile($root . '/.jira.yml');
    $arrayConfiguration = new ArrayConfiguration([
      'jiraHost' => $configuration['jira_url'],
      'jiraUser' => $configuration['jira_username'],
      'jiraPassword' => $configuration['jira_api_token'],
    ]);
    $linkService = new IssueLinkService($arrayConfiguration);
    $types = $linkService->getIssueLinkTypes();
    $table = new Table($output);
    $table->setHeaders(['ID', 'Link type']);
    foreach ($types as $type) {
      $table->addRow([
        $type->id,
        $type->name,
      ]);
    }
    $table->render();
    return 0;
  }

}
