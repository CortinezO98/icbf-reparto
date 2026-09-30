INSERT INTO catalog_items (catalog_id, code, label, sort_order, is_active)
SELECT c.id, 'OFFLINE', 'Desconectado', 999, 1
FROM catalogs c
WHERE c.code='AGENT_PRESENCE_STATUS'
ON DUPLICATE KEY UPDATE
    label=VALUES(label),
    sort_order=VALUES(sort_order),
    is_active=1;
