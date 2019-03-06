<?php
/**
 *
 * У<qwadmin.010xr.com>
 *     <hanchuan@010xr.com>
 *     2016-01-17
 *     1.0.0
 * dz
 *
 **/

namespace Qwadmin\Controller;

class LogoutController extends ComController
{
    public function index()
    {
        cookie('auth', null);
        session('uid',null);
        $url = U("login/index");
        header("Location: {$url}");
        exit(0);
    }
}