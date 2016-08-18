<?php

namespace ConnectHolland\AccompliDaemonTask;

use Accompli\AccompliEvents;
use Accompli\Deployment\Release;
use Accompli\EventDispatcher\Event\DeployReleaseEvent;
use Accompli\EventDispatcher\Event\LogEvent;
use Accompli\Task\AbstractConnectedTask;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Accompli Task to start and stop a daemon.
 *
 * @author Ron Rademaker
 */
class DaemonStopStartTask extends AbstractConnectedTask
{
    /**
     * Instruction to start the daemon.
     *
     * @var string
     */
    private $start;

    /**
     * Instruction to stop the daemon.
     *
     * @var string
     */
    private $stop;

    /**
     * Creates a new DaemonStopStartTask.
     *
     * @param $start
     * @param $stop
     */
    public function __construct($start, $stop)
    {
        $this->start = $start;
        $this->stop = $stop;
    }

    /**
     * Gets the events to subscribe to.
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            AccompliEvents::DEPLOY_RELEASE          => ['stopDaemon', 0],
            AccompliEvents::DEPLOY_RELEASE_FAILED   => ['startDaemonInCurrentRelease', 0],
            AccompliEvents::DEPLOY_RELEASE_COMPLETE => ['startDaemonInNewRelease', 0],
        ];
    }

    /**
     * Stops the configured daemon.
     *
     * @param DeployReleaseEvent
     */
    public function stopDaemon(DeployReleaseEvent $event, $eventName, EventDispatcherInterface $eventDispatcher)
    {
        if ($event->getCurrentRelease() instanceof Release) {
            $this->executeCommand($event->getCurrentRelease(), $eventDispatcher, $this->stop, $eventName);
        }
    }

    /**
     * Starts the configured daemon in the current (old) release.
     *
     * @param DeployReleaseEvent
     */
    public function startDaemonInCurrentRelease(DeployReleaseEvent $event, $eventName, EventDispatcherInterface $eventDispatcher)
    {
        $this->startDaemon($event->getCurrentRelease(), $eventDispatcher, $eventName);
    }

    /**
     * Starts the configured daemon in the new (just releaaed) release.
     *
     * @param DeployReleaseEvent
     */
    public function startDaemonInNewRelease(DeployReleaseEvent $event, $eventName, EventDispatcherInterface $eventDispatcher)
    {
        $this->startDaemon($event->getRelease(), $eventDispatcher, $eventName);
    }

    /**
     * Starts the daemon in $release.
     *
     *
     * @param Release                  $release
     * @param EventDispatcherInterface $eventDispatcher
     * @param string                   $eventName
     */
    private function startDaemon(Release $release, EventDispatcherInterface $eventDispatcher, $eventName)
    {
        $this->executeCommand($release, $eventDispatcher, $this->start, $eventName);
    }

    /**
     * Execute $command in $release.
     *
     * @param Release                  $release
     * @param EventDispatcherInterface $eventDispatcher
     * @param string                   $command
     * @param string                   $eventName
     */
    private function executeCommand(Release $release, EventDispatcherInterface $eventDispatcher, $command, $eventName)
    {
        $host = $release->getWorkspace()->getHost();
        $connection = $this->ensureConnection($host);
        $currentWorkingDirectory = $connection->getWorkingDirectory();
        $path = $release->getPath();
        $connection->changeWorkingDirectory($path);
        $result = $connection->executeCommand($command);

        if ($result->isSuccessful()) {
            $eventDispatcher->dispatch(
                AccompliEvents::LOG,
                new LogEvent(
                    LogLevel::DEBUG,
                    'Succeeded to control the daemon using {daemon}.',
                    $eventName,
                    $this,
                    [
                        'daemon' => $command,
                    ]
                )
            );
        } else {
            $eventDispatcher->dispatch(
                AccompliEvents::LOG,
                new LogEvent(
                    LogLevel::WARNING,
                    '{separator} Failed to control the daemon using {daemon} because {error}.{separator} ',
                    $eventName,
                    $this,
                    [
                        'daemon'    => $command,
                        'error'     => $result->getErrorOutput(),
                        'separator' => "\n=================\n",
                    ]
                )
            );
        }

        $connection->changeWorkingDirectory($currentWorkingDirectory);
    }
}
