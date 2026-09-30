UPDATE cases c
JOIN import_batches b ON b.id = c.source_batch_id
SET c.queue_id = b.queue_id
WHERE c.queue_id IS NULL
  AND b.queue_id IS NOT NULL;
