# Setup & Installation

Plain-language guides for putting the tourism portal onto a computer or a
server, for a reader with **no assumed technical background**. Every guide is
written in two languages that say exactly the same thing:

| | English | Русский |
| --- | --- | --- |
| Start here — what this is and which guide to follow | [`en/overview.md`](en/overview.md) | [`ru/overview.md`](ru/overview.md) |
| Run it on your own computer, the easy way (Docker) | [`en/local-with-docker.md`](en/local-with-docker.md) | [`ru/local-with-docker.md`](ru/local-with-docker.md) |
| Run it on your own computer, without Docker | [`en/local-without-docker.md`](en/local-without-docker.md) | [`ru/local-without-docker.md`](ru/local-without-docker.md) |
| Put it on a real server, the designed way (Docker) | [`en/production-with-docker.md`](en/production-with-docker.md) | [`ru/production-with-docker.md`](ru/production-with-docker.md) |
| Put it on a real server, without Docker | [`en/production-without-docker.md`](en/production-without-docker.md) | [`ru/production-without-docker.md`](ru/production-without-docker.md) |
| Check it worked, sign in, everyday tasks | [`en/first-run-and-daily-tasks.md`](en/first-run-and-daily-tasks.md) | [`ru/first-run-and-daily-tasks.md`](ru/first-run-and-daily-tasks.md) |
| When something does not work | [`en/troubleshooting.md`](en/troubleshooting.md) | [`ru/troubleshooting.md`](ru/troubleshooting.md) |

The English and Russian copies of each file carry the same file name and the
same steps. If you change one, change the other in the same edit.

## How this relates to the rest of `docs/`

- **These guides** get the site *running* the first time.
- [`../operations/`](../operations/) covers *day-to-day operations* once it
  runs — shipping a new version, rolling back, restoring from a backup,
  rotating a password, running a scheduled job by hand, reading a failed
  pipeline. Available in English, Russian, and a machine-addressed rendering
  for an AI assistant.
- The **system runbooks** listed in [`../README.md`](../README.md) are the
  deeper technical references (the database schema, backups, object storage
  and CDN, mail and error tracking, queues, worker capacity) for a
  developer.
