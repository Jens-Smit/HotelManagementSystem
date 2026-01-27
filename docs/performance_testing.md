# Performance Testing Checklist

## Database Query Performance

```bash
# Enable Symfony Profiler in dev environment
# Check for N+1 queries

# Test booking creation with profiler
curl -X POST http://localhost/api/bookings \
  -H "Content-Type: application/json" \
  -d '{"room_id":"...","start_date":"2025-06-01","end_date":"2025-06-05",...}'

# Check profiler: http://localhost/_profiler
# Verify:
# - Query count < 10
# - No duplicate queries
# - Proper use of indexes
```

## Concurrent Booking Test (Race Condition)

Use Apache JMeter or similar:

```xml
<!-- jmeter-concurrent-booking.jmx -->
<ThreadGroup>
  <numThreads>10</numThreads> <!-- 10 concurrent users -->
  <rampTime>1</rampTime>
  <HTTPSamplerProxy>
    <stringProp name="HTTPSampler.path">/api/bookings</stringProp>
    <stringProp name="HTTPSampler.method">POST</stringProp>
    <!-- Same room, same dates for all 10 threads -->
  </HTTPSamplerProxy>
</ThreadGroup>
```

**Expected Result:** Only ONE booking succeeds, others get 409 Conflict

## iCal Fetch Performance

```bash
# Test with real Booking.com/HomeAway URLs
time php bin/console app:sync-calendars --room-id=<UUID>

# Should complete in < 30 seconds per room
```

## Load Testing

```bash
# Test with 100 concurrent users
ab -n 1000 -c 100 -p booking_payload.json -T "application/json" http://localhost/api/bookings

# Should handle:
# - Response time < 2 seconds
# - Error rate < 1%
# - No memory leaks
```

## Caching Strategy

```bash
# Test availability caching
curl http://localhost/api/availability?room_id=...&start_date=...&end_date=...

# Verify:
# - First request: < 500ms
# - Subsequent requests: < 100ms
# - Cache hit rate > 90%
```

## Database Optimization

```sql
-- Check slow queries
SELECT query, execution_time
FROM performance_schema.slow_log
ORDER BY execution_time DESC
LIMIT 10;

-- Check index usage
EXPLAIN SELECT * FROM bookings
WHERE room_id = '...' AND start_date <= '...' AND end_date >= '...';
```

## Monitoring Metrics

Key metrics to monitor:
- **Response Time:** Average and 95th percentile
- **Throughput:** Requests per second
- **Error Rate:** Failed requests percentage
- **Database Performance:** Query execution time, connections
- **Memory Usage:** PHP memory consumption
- **CPU Usage:** Server CPU load