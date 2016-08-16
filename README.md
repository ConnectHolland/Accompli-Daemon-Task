# Accompli-Daemon-Task
Accompli task to manage daemons

## Configuration

``` json
{
  "class": "ConnectHolland\\AccompliDaemonTask\\DaemonStopStartTask",
  "stop": "bin/console rabbitmq-supervisor:control stop --env=prod",
  "start": "bin/console rabbitmq-supervisor:build --env=prod"
}
```
