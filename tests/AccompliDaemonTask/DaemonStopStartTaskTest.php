<?php

namespace ConnectHolland\AccompliDaemonTask\Test;

use Accompli\AccompliEvents;
use Accompli\Chrono\Process\ProcessExecutionResult;
use Accompli\Deployment\Host;
use Accompli\Deployment\Release;
use Accompli\Deployment\Workspace;
use Accompli\EventDispatcher\Event\DeployReleaseEvent;
use Accompli\EventDispatcher\EventDispatcherInterface;
use ConnectHolland\AccompliDaemonTask\DaemonStopStartTask;
use Mockery;
use PHPUnit_Framework_TestCase;

/**
 * Unit test for the stop start task.
 *
 * @author Ron Rademaker
 */
class DaemonStopStartTaskTest extends PHPUnit_Framework_TestCase
{
    /**
     * Executed command.
     *
     * @var string
     */
    private $executedCommand;

    /**
     * Release where the command was executed.
     *
     * @var string
     */
    private $release;

    /**
     * Tests if the expected event subscriptions exist.
     */
    public function testExpectedEventSubscriptions()
    {
        $expected = [
            AccompliEvents::DEPLOY_RELEASE          => 'stopDaemon',
            AccompliEvents::DEPLOY_RELEASE_FAILED   => 'startDaemonInCurrentRelease',
            AccompliEvents::DEPLOY_RELEASE_COMPLETE => 'startDaemonInNewRelease',
        ];

        $actual = DaemonStopStartTask::getSubscribedEvents();

        foreach ($expected as $event => $method) {
            $this->assertTrue(array_key_exists($event, $actual));
            $found = false;
            foreach ($actual as $subscribed) {
                if (is_array($subscribed)) {
                    $found = $found || ($subscribed[0] === $method);
                } else {
                    $found = $found || ($subscribed === $method);
                }
            }

            $this->assertTrue($found);
        }
    }

    /**
     * Tests stopping the daemon in the context of the current release.
     */
    public function testStopDaemonInCurrentRelease()
    {
        $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcher->shouldIgnoreMissing();

        $task = $this->getDaemonStopStartTask();
        $eventMock = $this->getEventMock();

        $task->stopDaemon($eventMock, AccompliEvents::DEPLOY_RELEASE, $eventDispatcher);

        $eventDispatcher->shouldHaveReceived('dispatch')->once(); // the log message
        $this->assertNotNull($this->executedCommand);
        $this->assertEquals('stop', $this->executedCommand);
        $this->assertNotNull($this->release);
        $this->assertEquals('current', $this->release);
    }

    /**
     * Tests starting the daemon in the context of the current release (after failed deployment).
     */
    public function testStartDaemonInCurrentRelease()
    {
        $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcher->shouldIgnoreMissing();

        $task = $this->getDaemonStopStartTask();
        $eventMock = $this->getEventMock();

        $task->startDaemonInCurrentRelease($eventMock, AccompliEvents::DEPLOY_RELEASE, $eventDispatcher);

        $eventDispatcher->shouldHaveReceived('dispatch')->once(); // the log message
        $this->assertNotNull($this->executedCommand);
        $this->assertEquals('start', $this->executedCommand);
        $this->assertNotNull($this->release);
        $this->assertEquals('current', $this->release);
    }

    /**
     * Tests starting the daemon in the context of the new release (after succefull deployment).
     */
    public function testStartDaemonInNewRelease()
    {
        $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcher->shouldIgnoreMissing();

        $task = $this->getDaemonStopStartTask();
        $eventMock = $this->getEventMock();

        $task->startDaemonInNewRelease($eventMock, AccompliEvents::DEPLOY_RELEASE, $eventDispatcher);

        $eventDispatcher->shouldHaveReceived('dispatch')->once(); // the log message
        $this->assertNotNull($this->executedCommand);
        $this->assertEquals('start', $this->executedCommand);
        $this->assertNotNull($this->release);
        $this->assertEquals('new', $this->release);
    }

    /**
     * Gets the task to test with.
     *
     * @return DaemonStopStartTask
     */
    private function getDaemonStopStartTask()
    {
        return new DaemonStopStartTask('start', 'stop');
    }

    /**
     * Gets the mocked event to test the task with.
     *
     * @return DeployReleaseEvent
     */
    private function getEventMock()
    {
        $eventMock = Mockery::mock(DeployReleaseEvent::class);

        $connectionMock = Mockery::mock();
        $connectionMock->shouldReceive('isConnected')->andReturn(true);
        $connectionMock->shouldReceive('executeCommand')->andReturnUsing(function ($command) {
            $this->executedCommand = $command;
            $result = Mockery::mock(ProcessExecutionResult::class);
            $result->shouldReceive('isSuccesful')->andReturn(true);
            $result->shouldIgnoreMissing();

            return $result;
        });
        $connectionMock->shouldReceive('getWorkingDirectory')->andReturn('');
        $connectionMock->shouldReceive('changeWorkingDirectory');

        $hostMock = Mockery::mock(Host::class);
        $hostMock->shouldReceive('hasConnection')->andReturn(true);
        $hostMock->shouldReceive('getConnection')->andReturn($connectionMock);

        $workspaceMock = Mockery::mock(Workspace::class);
        $workspaceMock->shouldReceive('getHost')->andReturn($hostMock);

        $release = Mockery::mock(Release::class);
        $release->shouldReceive('getWorkspace')->andReturn($workspaceMock);
        $release->shouldReceive('getPath')->andReturn('/the/full/path');

        $eventMock->shouldReceive('getCurrentRelease')->andReturnUsing(function () use ($release) {
            $this->release = 'current';

            return $release;
        });
        $eventMock->shouldReceive('getRelease')->andReturnUsing(function () use ($release) {
            $this->release = 'new';

            return $release;
        });

        return $eventMock;
    }
}
