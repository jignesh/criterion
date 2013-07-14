<?php
namespace Criterion\UI\Controller;

class TestController
{

    public function status(\Silex\Application $app)
    {
        $test = $app['mongo']->tests->findOne(array(
            '_id' => new \MongoId($app['request']->get('id'))
        ));

        $logs = $app['mongo']->logs->find(array(
            'test_id' => new \MongoId($test['_id']),
            'internal' => false
        ))->sort(array(
            'time' => 1
        ));

        $test['log'] = array();
        foreach ($logs as $log)
        {
            $test['log'][] = $log;
        }

        if ( ! $test)
        {
            return $app->abort(404, 'Test not found.');
        }

        $test['project'] = $app['mongo']->projects->findOne(array(
            '_id' => $test['project_id']
        ));

        $test['project']['_id'] = (string) $test['project']['_id'];
        $test['_id'] = (string) $test['_id'];

        return $app->json($test);
    }

    public function view(\Silex\Application $app)
    {
        $data['test'] = $app['mongo']->tests->findOne(array(
            '_id' => new \MongoId($app['request']->get('id'))
        ));

        if ( ! $data['test'])
        {
            return $app->abort(404, 'Test not found.');
        }

        $data['project'] = $app['mongo']->projects->findOne(array(
            '_id' => $data['test']['project_id']
        ));

        if ( ! $data['project'])
        {
            return $app->abort(404, 'Project not found.');
        }

        $logs = $app['mongo']->logs->find(array(
            'test_id' => new \MongoId($app['request']->get('id'))
        ));

        $data['log'] = array();
        foreach ($logs as $log)
        {
            $data['log'][] = $log;
        }

        $data['title'] = $data['test']['_id'] . ' | ' . $data['project']['short_repo'];

        return $app['twig']->render('Test.twig', $data);
    }

    public function delete(\Silex\Application $app)
    {
        $test = $app['mongo']->tests->findOne(array(
            '_id' => new \MongoId($app['request']->get('id'))
        ));

        if ( ! $test)
        {
            return $app->abort(404, 'Test not found.');
        }

        $app['mongo']->tests->remove(array(
            '_id' => new \MongoId($app['request']->get('id'))
        ));

        $app['mongo']->logs->remove(array(
            'test_id' => new \MongoId($app['request']->get('id'))
        ));

        return $app->redirect('/project/' . $test['project_id']);
    }
}