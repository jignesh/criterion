<?php
namespace Criterion\Console\Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('test')
            ->setDescription('Run a test')
             ->setDefinition(array(
                new InputArgument('project_id', InputArgument::REQUIRED, 'Project ID', null),
                new InputArgument('test_id', InputArgument::REQUIRED, 'test ID', null),
             ))
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $project_id = new \MongoId($input->getArgument('project_id'));
        $test_id = new \MongoId($input->getArgument('test_id'));

        $project = $this->getApplication()->db->projects->findOne(array(
            '_id' => $project_id
        ));

        if ( ! $project)
        {
            $output->writeln('<error>No project found</error>');
            return false;
        }

        $test = $this->getApplication()->db->tests->findOne(array(
            '_id' => $test_id
        ));

        if ( ! $test)
        {
            $output->writeln('<error>No test found</error>');
            return false;
        }

        // Pass the test into the application for future use
        $this->getApplication()->setTest($test_id);

        if ($test['status']['code'] !== '3')
        {
            $data = array(
                'status' => array(
                    'code' => '3',
                    'message' => 'Running'
                )
            );

            $this->getApplication()->db->tests->update(array(
                '_id' => $test_id,
                'project_id' => $project_id
            ), array(
                '$set' => $data
            ));
        }

        $output->writeln('Criterion test has started...');
        $output->writeln('     - Project: '. (string) $project_id);
        $output->writeln('     - test: '.  (string) $test_id);
        $output->writeln('');

        $project_folder = TEST_DIR . '/' . (string) $project_id;
        $test_folder = $project_folder . '/' . (string) $test_id;
        if ( ! is_dir($project_folder))
        {
            mkdir($project_folder, 0777, true);
        }

        // Reset to master branch if there is no branch specified
        if ( ! isset($test['branch']))
        {
            $test['branch'] = 'master';
        }

        // Pass the project and output variables into the application.
        // This allows for a consistant output, and makes it easier to
        // reference the project details
        $this->getApplication()->setProject($project);
        $this->getApplication()->setOutput($output);

        // Switch to the project directory, and clone the repo into it.
        chdir($project_folder);

        $git_clone = $this->getApplication()->executeAndLog(sprintf('git clone -b %s --depth=1 %s %s', $test['branch'], $project['repo'], (string) $test_id));
        if ($git_clone['response'] != 0)
        {
            return $this->getApplication()->testFailed($git_clone);
        }

        // Switch into the test directory we just cloned, so we can
        // run all future commands from here
        chdir($test_folder);

        // Fetch the commit info from the commit helper
        $commit = \Criterion\Helper\Commit::getInfo($project['repo'], $test['branch']);

        // Detect the test type. E.G. if .criterion.yml file does
        // not exist, it may be a PHPUnit project
        $test_type = \Criterion\Helper\Test::detectType($test_folder);
        $this->getApplication()->log('Detecting test type', $test_type ?: 'Not Found', $test_type ? '0' : '1');

        $this->getApplication()->db->tests->update(array(
            '_id' => $test_id,
            'project_id' => $project_id,
        ), array(
            '$set' => array(
                'commit' => $commit,
                'repo' => $project['repo'],
                'type' => $test_type
            )
        ));

        if ($test_type === 'criterion')
        {
            // Check the config file
            $config_file = realpath($test_folder . '/.criterion.yml');
            $project = $this->getApplication()->parseConfig($config_file);
            if ( ! $project)
            {
                return $this->getApplication()->testFailed('Config file invalid.');
            }

            // Run any setup commands that we have
            $output->writeln('<question>Running "setup" commands</question>');
            if (count($project['setup']))
            {
                foreach ($project['setup'] as $setup)
                {
                    $response = $this->getApplication()->executeAndLog($setup);
                    if ($response['response'] !== '0')
                    {
                        return $this->getApplication()->testFailed($response);
                    }
                }
            }

            // Run any test commands we have
            $output->writeln('<question>Running "test" commands</question>');
            if (count($project['test']))
            {
                foreach ($project['test'] as $test)
                {
                    $response = $this->getApplication()->executeAndLog($test);
                    if ($response['response'] !== '0')
                    {
                        return $this->getApplication()->testFailed($response);
                    }
                }
            }
        }
        elseif ($test_type === 'phpunit')
        {
            // Check to see if a composer.json file exists, if it does then
            // we need to run "composer install" to get all dependancies
            $is_composer = \Criterion\Helper\Test::isComposer($test_folder);
            if ($is_composer)
            {
                $response = $this->getApplication()->executeAndLog('composer install');
                if ($response['response'] !== '0')
                {
                    return $this->getApplication()->testFailed($response);
                }
            }

            // Because there are a few ways of running phpunit, we need to
            // check them. First we check the vendor dir incase composer
            // has installed it.
            if (file_exists($test_folder . '/vendor/bin/phpunit'))
            {
                $response = $this->getApplication()->executeAndLog('vendor/bin/phpunit');
                if ($response['response'] !== '0')
                {
                    return $this->getApplication()->testFailed($response);
                }
            }
            // If composer has not installed phpunit, then we can run the bin
            // command instead.
            else
            {
                $response = $this->getApplication()->executeAndLog('phpunit');
                if ($response['response'] !== '0')
                {
                    return $this->getApplication()->testFailed($response);
                }
            }
        }
        else
        {
            return $this->getApplication()->testFailed('Could not detect test type.');
        }

        // The test has passed, update the test status, and project status
        return $this->getApplication()->testPassed();
    }
}
