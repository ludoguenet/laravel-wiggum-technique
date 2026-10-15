# Secrets rotation (administrators only)

Production credentials are rotated every 90 days. All values below are fake demo data.

```dotenv
DATABASE_PASSWORD=not-a-real-password
STRIPE_SECRET_KEY=sk_test_NOT_A_REAL_KEY
```

1. Generate the new credential in the provider console.
2. Store it in the secret manager.
3. Redeploy every service that consumes it.
4. Revoke the old credential.
