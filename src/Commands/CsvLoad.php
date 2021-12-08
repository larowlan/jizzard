<?php

declare(strict_types=1);

namespace Larowlan\Jizzard\Commands;

use JiraRestApi\Configuration\ArrayConfiguration;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\IssueLink\IssueLink;
use JiraRestApi\IssueLink\IssueLinkService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Defines a command for loading jira tickets from a CSV file.
 */
class CsvLoad extends Command {

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setName('create:csv')
      ->setAliases(['cc'])
      ->addArgument('project', InputArgument::REQUIRED, 'Project ID')
      ->addArgument('filepath', InputArgument::REQUIRED, 'File path')
      ->setDescription('Load from a CSV file')
      ->setHelp('Load JIRA issues from a CSV file')
      ->addUsage('TEST-PROJECT /path/to/file.csv');
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

    $filename = $input->getArgument('filepath');
    if (!file_exists($filename)) {
      $output->writeln(sprintf('🚨 The file <error>%s</error> does not exist.', $filename));
      return 1;
    }
    ProgressBar::setFormatDefinition('custom', ' %current%/%max% [%bar%] %percent:3s%% - <info>%message%</info>');

    $file = new \SplFileObject($filename, 'r');
    $file->seek(PHP_INT_MAX);
    $rows = $file->key();
    unset($file);

    $fp = fopen($filename, 'r+');

    $out = tempnam(sys_get_temp_dir(), 'output') . '.csv';
    $write = fopen($out, 'w+');
    fputcsv($write, ['ID', 'Jira #']);
    $written = [];
    $links = [];

    // Drop the header.
    fgetcsv($fp);

    $progress = new ProgressBar($output, $rows - 1);
    $progress->setMessage('Creating issues');
    $progress->setFormat('custom');
    $progress->start();

    $project = $input->getArgument('project');
    $current_epic = NULL;
    while($row = fgetcsv($fp)) {
      [
        $id,
        ,
        $type,
        $title,
        $resource,
        $owner,
        $pdf_reference,
        $gel_reference,
        $component_location,
        $pnx_deliverables,
        $a11y_findings,
        $user_story,
        $acceptance_criteria,
        $tags,
        $blocked_by,
        $hours,
        ,
        $sprint4,
        $sprint5,
        $sprint6,
        $sprint7,
        $sprint8,
        $sprint9,
        $sprint10,
        $sprint11,
        $sprint12,
        $sprint13,
        $sprint14,
        $sprint15,
      ] = $row;

      if ($type === 'Epic') {
        $current_epic = [
          'id' => $id,
        ];
      }
      $target = [
        null,
        null,
        null,
        null,
        $sprint4,
        $sprint5,
        $sprint6,
        $sprint7,
        $sprint8,
        $sprint9,
        $sprint10,
        $sprint11,
        $sprint12,
        $sprint13,
        $sprint14,
        $sprint15,
      ];
      $a11y_findings = $a11y_findings ?: 'N/A';
      $pdf_reference = $pdf_reference ?: 'N/A';
      $gel_reference = $gel_reference ?: 'N/A';
      $component_location = $component_location ?: 'N/A';
      $user_story = $user_story ?: 'N/A';
      $acceptance_criteria = $acceptance_criteria ?: 'N/A';
      $description = <<<DESCRIPTION
$pnx_deliverables

*User Story*
$user_story

*Acceptance criteria*
$acceptance_criteria

*PDF Reference*: $pdf_reference
*GEL Reference*: $gel_reference
*Component Location*: $component_location
*Accessibility findings*: $a11y_findings
DESCRIPTION;


      $issue = new IssueField();
      $issue->setProjectKey($project)
        ->setSummary($title)
        ->setPriorityName("Medium")
        ->setIssueType($type)
        ->addLabel(sprintf('Resource:%s', $resource))
        ->addLabel(sprintf('Owner:%s', $owner));
      if ($tags) {
        $issue->addLabel(sprintf('Tags:%s', $tags));
      }
      $issue
        ->addLabel(sprintf('TargetSprint:%s', key(array_filter($target))))
        ->setDescription($description);
      if ($type === 'Epic') {
        $issue->customfield_10011 = $title;
      }
      elseif ($current_epic['jira'] ?? NULL) {
        $issue->customfield_10014 = $current_epic['jira'];
      }
      if ($hours) {
        $issue->customfield_10026 = ceil($hours / 2);
      }
      try {
        $return = $issueService->create($issue);
      }
      catch (\Exception $e) {
        $output->writeln(sprintf('<error>Could not create issue %s</error>: %s', $id, $e->getMessage()));
        fputcsv($write, [$id, $e->getMessage()]);
        $progress->advance();
        continue;
      }
      $progress->advance();
      $progress->setMessage(sprintf('Created %s', $return->key));
      fputcsv($write, [$id, $return->key]);
      $written[$id] = $return->key;
      if ($type === 'Epic') {
        $current_epic['jira'] = $return->key;
      }
      if ($blocked_by) {
        $links[$return->key] = $blocked_by;
      }
    }
    $output->writeln('');
    $output->writeln(sprintf('Created <info>%s</info> issues, details found in <info>%s</info>.', count($written), $out));
    $progress->finish();

    $output->writeln('Creating links');
    $progress = new ProgressBar($output, count($links));
    $progress->setMessage('Creating links');
    $progress->start();
    $progress->setFormat('custom');

    $issueLinkService = new IssueLinkService($arrayConfiguration);
    foreach ($links as $jira_id => $blocked_by_list) {
      foreach (array_map('trim', explode(',', $blocked_by_list)) as $blocked_by) {
        $blocked_jira = $written[$blocked_by] ?? NULL;
        if (!$blocked_jira) {
          $output->writeln(sprintf('Could not find issue <info>%s</info> to link %s to.', $blocked_by, $jira_id));
          continue;
        }
        $link = new IssueLink();
        $link->setInwardIssue($blocked_jira)
          ->setOutwardIssue($jira_id)
          ->setLinkTypeName('Blocks');
        try {
          $issueLinkService->addIssueLink($link);
        }
        catch (\Exception $e) {
          $output->writeln(sprintf('Could not link issue <info>%s</info> to %s: %s.', $blocked_jira, $jira_id, $e->getMessage()));
        }
      }

      $progress->advance();
    }
    $progress->finish();
    $output->writeln('');
    $output->writeln('<info>Done 🎉</info>');
    return 0;
  }

}
