# 04 · Migración SQL versionada

Ejemplo de migración de esquema en la iteración v3 del sistema de afiliados. Añade dos capacidades al modelo de datos: anular comisiones cuando se produce un reembolso, y datos de cobro por afiliado.

## Fichero

- **`migration_afiliados_v3.sql`** — Script `ALTER TABLE` idempotente-por-versión, para ejecutar una vez sobre producción.

## Qué demuestra

- **Disciplina de migraciones**: cada cambio de esquema se versiona (`_v1`, `_v2`, `_v3`) y se guarda como fichero independiente. El nombre del fichero cuenta qué feature introdujo el cambio, no solo cuándo.
- **Cambios documentados en el propio SQL**: cabecera explicando qué añade cada `ALTER` y por qué. En 6 meses, cualquiera (incluido el yo del futuro) puede leer solo el fichero y entender el cambio sin cruzar con otros artefactos.
- **Posicionamiento explícito de columnas** (`AFTER pagada_referencia`, `AFTER notas`): agrupa columnas relacionadas visualmente al inspeccionar la tabla en phpMyAdmin. Detalle pequeño pero que ayuda al mantenimiento.
- **Índice donde toca**: `ADD KEY idx_anulada` porque la columna `anulada` se filtra constantemente en el dashboard del afiliado para excluir comisiones canceladas.
- **`ENUM` para valores cerrados**: `payout_method ENUM('transferencia','bizum','paypal')` valida en BD que solo hay tres métodos posibles, evitando errores de tipeo en el código de aplicación.

## Contexto

Migración aplicada en mayo de 2026 para soportar reembolsos que reviertan comisiones ya calculadas — feature que apareció cuando llegó el primer reembolso real y descubrí que el sistema no lo contemplaba. Buen ejemplo de cómo los requisitos aparecen en producción, no en el diseño inicial.
