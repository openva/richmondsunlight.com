Re-apply test user fixtures to the Docker database. Run this after a Docker volume reset or if test users are missing.

```bash
docker compose exec -T db mysql -u ricsun -ppassword richmondsunlight < deploy/mysql/test-users.sql
```
