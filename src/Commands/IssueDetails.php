<?php

declare(strict_types=1);

namespace Larowlan\Jizzard\Commands;

use JiraRestApi\Configuration\ArrayConfiguration;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\IssueType;
use JiraRestApi\Issue\TimeTracking;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Defines a command for debugging an issue.
 */
class IssueDetails extends Command {

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setName('info:issue')
      ->setAliases(['ii'])
      ->addArgument('issue_id', InputArgument::REQUIRED, 'Issue ID')
      ->setDescription('Issue information')
      ->setHelp('Show issue information')
      ->addUsage('BU-5');
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
    $issueService = new IssueService($arrayConfiguration);
    $issue = $issueService->get($input->getArgument('issue_id'));
    $table = new Table($output);
    $table->setHeaders(['Field', 'Value']);
    foreach ($issue->fields as $name => $value) {
      if ($value instanceof TimeTracking) {
        continue;
      }
      if ($name === 'worklog') {
        continue;
      }
      $table->addRow([
        $name,
        $this->printValue($value),
      ]);
    }
    $table->render();
    return 0;
  }

  /**
   * ${CARET}.
   *
   * @param $value
   *
   * @return string|null
   */
  protected function printValue($value): ?string {
    if (is_null($value)) {
      return '';
    }
    if (is_scalar($value)) {
      return (string) $value;
    }
    if (is_array($value)) {
      return print_r($value, TRUE);
    }
    if (property_exists($value, 'name')) {
      return $value->name;
    }
    try {
      $value = print_r($value, TRUE);
      return $value;
    }
    catch (\Error $e) {
      // Gulp.
    }
    return sprintf('Could not output %s', get_class($value));
  }

}
