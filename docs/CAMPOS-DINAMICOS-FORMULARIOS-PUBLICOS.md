# Campos dinamicos en formularios publicos

Al enviar un formulario publicado, el backend obtiene sus campos activos desde `campaign_form_fields` y valida cada clave segun su tipo, obligatoriedad y configuracion.

Los campos personalizados se guardan en `form_submissions.payload` y no se escriben como columnas de `orders`. El payload conserva tambien los campos fijos validados y `submission_key`.

Se admiten los tipos `text`, `textarea`, `number`, `date`, `phone`, `select` y `time`. Los campos `select` utilizan `config.options`; los campos de texto pueden utilizar `max_length` en la configuracion del formulario o en `validation_rules`.

Las claves desconocidas, inactivas o no asociadas al formulario se rechazan con HTTP 422. Las claves reservadas del sistema no pueden utilizarse como campos personalizados.
