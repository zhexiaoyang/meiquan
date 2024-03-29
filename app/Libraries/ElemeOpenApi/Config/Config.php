<?php

namespace App\Libraries\ElemeOpenApi\Config;

use InvalidArgumentException;

class Config
{
    private $app_key;
    private $app_secret;
    public $sandbox;
    private $request_url;
    private $log;
    private $default_request_url = "https://open-api.shop.ele.me";
    private $default_sandbox_request_url = "https://open-api-sandbox.shop.ele.me";

    public function __construct($app_key, $app_secret, $sandbox = false)
    {
        if ($sandbox == false) {
            $this->request_url = $this->default_request_url;
        } elseif ($sandbox == true) {
            $this->request_url = $this->default_sandbox_request_url;
        } else {
            throw new InvalidArgumentException("the type of sandbox should be a boolean");
        }

        if ($app_key == null || $app_key == "") {
            throw new InvalidArgumentException("app_key is required");
        }

        if ($app_secret == null || $app_secret == "") {
            throw new InvalidArgumentException("app_secret is required");
        }

        $this->app_key = $app_key;
        $this->app_secret = $app_secret;
        $this->sandbox = $sandbox;
        $this->log = true;
    }

    public function get_app_key()
    {
        return $this->app_key;
    }

    public function get_app_secret()
    {
        return $this->app_secret;
    }

    public function get_request_url()
    {
        return $this->request_url;
    }

    public function set_request_url($request_url)
    {
        $this->request_url = $request_url;
    }

    public function get_log()
    {
        return $this->log;
    }

    public function set_log($log)
    {
        if (!method_exists($log, "info")) {
            throw new InvalidArgumentException("logger need have method 'info(\$message)'");
        }
        if (!method_exists($log, "error")) {
            throw new InvalidArgumentException("logger need have method 'error(\$message)'");
        }
        $this->log = $log;
    }
}
