# t3n.JobQueue.RabbitMQ

A job queue backend for the [Flowpack.JobQueue.Common](https://github.com/Flowpack/jobqueue-common) package based on [RabbitMQ](https://www.rabbitmq.com).

Disclaimer: This is a firk/remake of an initial version wich was made by [ttreeagency](https://github.com/ttreeagency/)!

## Setup

To use RabbitMQ as a backend for a JobQueue you need a running RabbitMQ Service. You can use our docker image which also
includes the management console [t3n/rabbitmq](https://quay.io/repository/t3n/rabbitmq)

Install the package using composer:

```
composer require t3n/jobqueue-rabbitmq
```

## Configuration

RabbitMQ allows a wide range of configurations. Have a look at `Settings.yaml` for an overview with all configuration
options.

The easiest configuration for a simple queue can be configured like this:

```yaml
Flowpack:
  JobQueue:
    Common:
      queues:
        className: 't3n\JobQueue\RabbitMQ\Queue\RabbitQueue'
        executeIsolated: true

        options:
          queueOptions:
            # we want the queue to persist messages so they are stored even if no consumer (aka worker) is connected
            durable: true
            # multiple worker should be able to consume a queue at the same time
            exclusive: false
            autoDelte: false

          client:
            host: localhost
            port: 5672
            username: guest
            password: guest
```

A more complex configuration could also configure an exchange and some routing keys.
In this example we will configure one exchange wich all producers connect to. If your
RabbitMQ server has the `rabbitmq_delayed_message_exchange` plugin (our docker image ships
it by default) enabled we can also use the delay feature.

This configuration will configure several queues along the exchange. Message are routed 
to the queue based on a `routingKey`. We will use three parts: `<project>.<type>.<name>`.
We will use the type `job` to indicate it's a job to execute. We could also use something like
log or whatever. The shape of the routing key is up to you.

So after all we will configure several producers/job queues.
The producer queues are meant to be used by the `$jobManager->queue()`.
The consumer queue will be used within the `./flow job:work` command.

In this example we will end up with up with two queues. One queue that with all messages matching
the routing key `vendor.jobs.*`, so whenever the `producer-import` or `producer-tags` queue is used.
And another queue that fetches all jobs for all vendors.

To actually start a consumer run `./flow job:work --queue all-jobs`.

This pattern enables you to implement different kind of advanced pub/sup implementations.

```yaml
Flowpack:
  JobQueue:
    Common:
      presets:
        rabbit:
          className: 't3n\JobQueue\RabbitMQ\Queue\RabbitQueue'
          executeIsolated: true
          maximumNumberOfReleases: 3

          releaseOptions:
            delay: 5

          options:
            routingKey: ''

            queueOptions:
              # don't declare a queue by default
              # All messages are forwarded to the exchange "t3n"
              declare: false
              exchangeName: 't3n'
              # the queue should be durable and don't delete itself
              durable: true
              autoDelte: false
              # several worker are allowed per queue
              exclusive: false

            exchange:
              name: 't3n'
              # this exchange should support delayed messages
              type: 'x-delayed-message'
              passive: false
              durable: true
              autoDelete: false
              arguments:
                # the origin type is topic so we can use routingkeys including `*` or `#`
                x-delayed-type: 'topic'

            client:
              host: localhost
              port: 5672
              username: guest
              password: guest

      queues:
        producer-import:
          preset: rabbit
          options:
            routingKey: 'vendor.jobs.import'

        producer-tags:
          preset: rabbit
          options:
            routingKey: 'vendor.jobs.tags'

        vendor-jobs:
          preset: rabbit
          options:
            routingKey: 'vendor.jobs.*'
            queueOptions:
              # This queue should actually be declared
              declare: true

        all-jobs:
          preset: rabbit
          options:
            routingKey: '*.jobs.*'
            queueOptions:
              # This queue should actually be declared
              declare: true
```