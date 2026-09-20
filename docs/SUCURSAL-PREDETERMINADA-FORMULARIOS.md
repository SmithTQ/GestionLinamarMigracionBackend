# Sucursal predeterminada de formularios

La creación de formularios permite omitir `branch_id`. En ese caso, el backend busca una sucursal activa cuyo código coincida con `FORMS_DEFAULT_BRANCH_CODE`.

El valor predeterminado es `CENTRAL`:

```env
FORMS_DEFAULT_BRANCH_CODE=CENTRAL
```

La sucursal debe existir y estar activa antes de crear el formulario. Al crear el formulario sin `branch_id`, el backend conserva el identificador internamente y asocia la sucursal a la campaña sin duplicar la relación.

Si se envía `branch_id`, se mantiene el comportamiento anterior: la sucursal debe existir y pertenecer a la campaña.
