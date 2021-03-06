<?php 
namespace WHMCS\MarketConnect;


class Api
{
    const MARKETPLACE_LIVE_URL = "https://marketplace.whmcs.com/api/";
    const MARKETPLACE_TESTING_URL = "https://hou-1.frontend.marketplace.testing.whmcs.com/api/";
    const MARKETPLACE_API_VERSION = "v1";

    public function link($email, $password, $licenseKey, $agreetos)
    {
        return $this->post("/link", array( "email" => $email, "password" => $password, "license_key" => $licenseKey, "agree_tos" => $agreetos ), 15);
    }

    public function register($firstname, $lastname, $company, $email, $password, $licenseKey, $agreetos)
    {
        return $this->post("/register", array( "first_name" => $firstname, "last_name" => $lastname, "company_name" => $company, "email" => $email, "password" => $password, "license_key" => $licenseKey, "agree_tos" => $agreetos ), 20);
    }

    public function balance()
    {
        return $this->get("/balance", array(  ), 10);
    }

    public function services()
    {
        $response = $this->get("/services");
        return $response;
    }

    public function activate($service)
    {
        $response = $this->post("/services/activate", array( "service" => $service ));
        return $response;
    }

    public function deactivate($service)
    {
        $response = $this->post("/services/deactivate", array( "service" => $service ));
        return $response;
    }

    public function purchase($service, $term)
    {
        $response = $this->post("/order", array( "service" => $service, "term" => $term ));
        return $response;
    }

    public function configure(array $configurationData)
    {
        $response = $this->post("/order/configure", $configurationData);
        return $response;
    }

    public function renew($orderNumber, $term)
    {
        $response = $this->post("/order/renew", array( "order_number" => $orderNumber, "term" => $term ));
        return $response;
    }

    public function extra($function, array $params = array(  ))
    {
        $response = $this->post("/order/" . $function, $params);
        return $response;
    }

    public function cancel($orderNumber)
    {
        return $this->post("/order/cancel", array( "order_number" => $orderNumber ));
    }

    public function status($orderNumber)
    {
        return $this->get("/order/" . $orderNumber);
    }

    public function sso()
    {
        return $this->get("/sso");
    }

    public function ssoForService($service)
    {
        return $this->get("/sso/" . $service);
    }

    public function ssoForOrder($orderNumber)
    {
        return $this->get("/order/sso/" . $orderNumber);
    }

    public function upgrade($orderNumber, $service, $term)
    {
        return $this->post("/order/upgrade", array( "order_number" => $orderNumber, "service" => $service, "term" => $term ));
    }

    protected function get($action, array $data = array(  ), $timeout = NULL)
    {
        return $this->call($action, "GET", $data, $timeout);
    }

    protected function post($action, array $data = array(  ), $timeout = NULL)
    {
        return $this->call($action, "POST", $data, $timeout);
    }

    protected function useMarketplaceTestingEnv()
    {
        $config = \DI::make("config");
        return (bool) $config->use_marketplace_testing_env;
    }

    protected function getApiUrl()
    {
        if( $this->useMarketplaceTestingEnv() ) 
        {
            return self::MARKETPLACE_TESTING_URL . self::MARKETPLACE_API_VERSION;
        }

        return self::MARKETPLACE_LIVE_URL . self::MARKETPLACE_API_VERSION;
    }

    protected function call($action, $method, $data, $timeout = 300)
    {
        if( $action != "/link" && $action != "/register" && !MarketConnect::isAccountConfigured() ) 
        {
            throw new Exception\AuthNotConfigured("Authentication failed. Please navigate to Setup > MarketConnect to resolve.");
        }

        $curl = curl_init();
        curl_setopt_array($curl, array( CURLOPT_URL => $this->getApiUrl() . $action, CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => "", CURLOPT_MAXREDIRS => 1, CURLOPT_TIMEOUT => $timeout, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_POSTFIELDS => http_build_query($data), CURLOPT_SSL_VERIFYPEER => !$this->useMarketplaceTestingEnv(), CURLOPT_SSL_VERIFYHOST => (!$this->useMarketplaceTestingEnv() ? 2 : 0), CURLOPT_HTTPHEADER => array( "Authorization: Bearer " . MarketConnect::getApiBearerToken() ) ));
        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        $curlErrorNum = curl_errno($curl);
        $responsecode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $decoded = json_decode($response, true);
        $replaceVars = array(  );
        if( array_key_exists("password", $data) ) 
        {
            $replaceVars[] = $data["password"];
        }

        if( array_key_exists("token", $decoded) ) 
        {
            $replaceVars[] = $decoded["token"];
        }

        $responseToLog = $response;
        if( !$responseToLog && $curlError ) 
        {
            $responseToLog = "CURL Error " . $curlErrorNum . " - " . $curlError;
        }

        logModuleCall("marketconnect", $action, $data, $responseToLog, $decoded, $replaceVars);
        if( $curlError ) 
        {
            throw new Exception\ConnectionError("Error Code: " . $curlErrorNum . " - " . $curlError);
        }

        if( $responsecode == 401 ) 
        {
            throw new Exception\AuthError($decoded["error"]);
        }

        if( $responsecode != 200 ) 
        {
            throw new Exception\GeneralError($decoded["error"]);
        }

        return $decoded;
    }

}


