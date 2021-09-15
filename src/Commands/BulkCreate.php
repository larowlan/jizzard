<?php

declare(strict_types=1);

namespace Larowlan\Jizzard\Commands;

use JiraRestApi\Configuration\ArrayConfiguration;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\Issue\IssueService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;

/**
 * Defines a bulk creation command.
 */
class BulkCreate extends Command {

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setName('create:bulk')
      ->setAliases(['cb'])
      ->setDescription('Bulk create jira tickets')
      ->setHelp('Bulk create jira tickets. <comment>Usage:</comment> <info>jizzard create:bulk</info>')
      ->addUsage('jizzard create:bulk');
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

    $helper = $this->getHelper('question');
    $default = sprintf('Drupal core security release %s', (new \DateTime())->format('d-m-Y'));
    $question = new Question(
      sprintf('Enter the issue title <comment>[%s]</comment>: ', $default),
      $default
    );
    $title = $helper->ask($input, $output, $question);

    $issues = [];
    $desription = file_get_contents($root . '/template.txt');
    foreach (Yaml::parseFile($root . '/projects.yml')['projects'] as $project) {
      $issue = new IssueField();
      $issue->setProjectKey($project)
        ->setSummary($title)
        ->setPriorityName("Medium")
        ->setIssueType("Task")
        ->setDescription($desription);
      $issues[] = $issue;
    }

    $ret = $issueService->createMultiple($issues);
    if ($ret) {
      $output->writeln(sprintf('<comment>Created %d issues:</comment>', count($ret)));
    }
    foreach ($ret as $issue) {
      $output->writeln(sprintf('%s/browse/%s', $configuration['jira_url'], $issue->key));
    }
    return 0;
  }

}
