# Monitoring

All services report to the internal monitoring stack.

- **Error rate:** alert above 1% of requests over 5 minutes.
- **Latency:** p95 must stay below 400 ms on the public API.
- **Queues:** alert when a queue has more than 1,000 pending jobs for 10 minutes.

Health checks are exposed on `/up` for every service. Logs are searchable for 14 days.
