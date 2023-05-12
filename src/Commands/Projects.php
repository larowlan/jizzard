<?php

declare(strict_types=1);

namespace Larowlan\Jizzard\Commands;

use JiraRestApi\Configuration\ArrayConfiguration;
use JiraRestApi\Project\ProjectService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Defines a class for projects.
 */
final class Projects extends Command {

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this->setName('projects');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $helper = new Table($output);
    $helper->setHeaders(['ID', 'Name']);
    $root = dirname(__DIR__, 2);
    $configuration = Yaml::parseFile($root . '/.jira.yml');
    $arrayConfiguration = new ArrayConfiguration([
      'jiraHost' => $configuration['jira_url'],
      'jiraUser' => $configuration['jira_username'],
      'jiraPassword' => $configuration['jira_api_token'],
    ]);
    $project_service = new ProjectService($arrayConfiguration);
    foreach ($project_service->getAllProjects() as $id => $project) {
      $helper->addRow([$project->id, $project->name]);
    }
    $helper->render();
    return 0;
  }

}
