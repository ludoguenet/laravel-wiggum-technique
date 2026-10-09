# Incident response

When production misbehaves, follow this runbook.

1. Acknowledge the alert in the on-call channel within 5 minutes.
2. Open an incident document and name an incident commander.
3. Check the monitoring dashboards (see the *Monitoring* guide).
4. If the last deployment is the suspect, roll back by re-deploying the previous tag.
5. Communicate every 30 minutes until the incident is resolved.
6. Write a blameless post-mortem within 3 working days.
