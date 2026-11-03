<?php

/**
 * ResellerSessionController — endpoint JSON de comprobación de sesión.
 *
 * Lo usa la función JS pingSession() (del footer) como keep-alive
 * de comprobación: GET /reseller/session → {"result": true}.
 *
 * Si la sesión expiró o no es válida, el bootstrap (session.php)
 * redirigirá al login ANTES de que lleguemos aquí.
 * Por eso, si el controlador se ejecuta — la sesión está activa.
 *
 * @see src/reseller/session.php  (legacy endpoint, nginx direct access)
 * @see src/public/Views/layouts/reseller/footer.php  (pingSession JS)
 */
class ResellerSessionController extends BaseResellerController
{
    public function index()
    {
        echo json_encode(['result' => true]);
        exit;
    }
}
