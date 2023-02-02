<?php

declare(strict_types=1);

namespace Larowlan\Jizzard\Commands;

use JiraRestApi\Configuration\ArrayConfiguration;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\Issue\IssueService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;

/**
 * Defines a bulk creation command.
 */
class BulkCreateFromTemplate extends Command {

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setName('create:bulk-from-template')
      ->setAliases(['cbt'])
      ->setDescription('Bulk create jira tickets from a template')
      ->setHelp('Bulk create jira tickets. <comment>Usage:</comment> <info>jizzard create:bulk [template number]</info>')
      ->addArgument('template', InputArgument::REQUIRED, 'Template issue ID')
      ->addUsage('jizzard create:bulk-from-template OPS-206');
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

    $template = $issueService->get($input->getArgument('template'));

    if (!$template) {
      $output->writeln(sprintf('<error>😭 No such issue %s</error>', $input->getArgument('template')));
      return 1;
    }
    $helper = $this->getHelper('question');
    $question = new Question(
      sprintf('Enter the issue title: '),
    );
    $title = $helper->ask($input, $output, $question);
    if (!$title) {
      $output->writeln('<error>😭 No title provided</error>');
      return 1;
    }

    $issues = [];
    foreach (Yaml::parseFile($root . '/projects.yml')['projects'] as $project) {
      $issue = new IssueField();
      $issue->setProjectKey($project)
        ->setSummary($title)
//        ->setPriorityName("Medium")
        ->setIssueType("Task")
        ->setDescription($template->fields->description);
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
