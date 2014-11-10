<?php

/**
 * A single class PHP wrapper for the Kayako helpdesk
 * @author James Hadley http://www.loadingdeck.com/ (with some code from Kayako's own implementation)
 */
class Kayako
{
    private $api_key;
    private $api_sec;
    private $api_path;

    /**
     * @param $key string Your Kayako API key
     * @param $secret string Your Kayako API secret
     * @param $path string The path to your Kayako API (e.g. https://you.com/kayako/api/index.php)
     */
    public function __construct($key, $secret, $path)
    {
        $this->api_key  = $key;
        $this->api_sec  = $secret;
        $this->api_path = $path;
    }

    /**
     * @param $query string The part of the API that deals with this request (e.g. /Tickets/Ticket)
     * @param $args array An associative array of parameters
     * @param $method string GET/POST/PUT/DELETE
     * @return array
     */
    public function send($query, $args, $method)
    {
        $salt   = mt_rand();
        $keys   = array('apikey' => $this->api_key, 'salt' => $salt, 'signature' => $this->generateHash($salt));
        $params = array_merge($args, $keys);

        if($method == 'POST' || $method == 'PUT')
        {
            $url = sprintf('%s?e=%s', $this->api_path, $query);
            $ch  = curl_init($url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } else {
            $url = sprintf('%s?e=%s&%s', $this->api_path, $query, http_build_query($params));
            $ch  = curl_init($url);
        }

        // Send
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        return $this->xmlToArray(curl_exec($ch));
    }

    private function generateHash($salt)
    {
        $signature        = hash_hmac('sha256', $salt, $this->api_sec, true);
        $encodedSignature = base64_encode($signature);

        return $encodedSignature;
    }
    private function xmlToArray($xml, $namespaces = null)
    {
        $iter = 0;
        $arr = array();
        if (is_string($xml))
            $xml = new SimpleXMLElement($xml);
        if (!($xml instanceof SimpleXMLElement))
            return $arr;
        if ($namespaces === null)
            $namespaces = $xml->getDocNamespaces(true);
        foreach ($xml->attributes() as $attributeName => $attributeValue) {
            $arr["_attributes"][$attributeName] = trim($attributeValue);
        }
        foreach ($namespaces as $namespace_prefix => $namespace_name) {
            foreach ($xml->attributes($namespace_prefix, true) as $attributeName => $attributeValue) {
                $arr["_attributes"][$namespace_prefix.':'.$attributeName] = trim($attributeValue);
            }
        }
        $has_children = false;
        foreach ($xml->children() as $element) {
            $has_children = true;
            $elementName = $element->getName();
            if ($element->children()) {
                $arr[$elementName][] = $this->xmlToArray($element, $namespaces);
            } else {
                $shouldCreateArray = array_key_exists($elementName, $arr) && !is_array($arr[$elementName]);
                if ($shouldCreateArray) {
                    $arr[$elementName] = array($arr[$elementName]);
                }
                $shouldAddValueToArray = array_key_exists($elementName, $arr) && is_array($arr[$elementName]);
                if ($shouldAddValueToArray) {
                    $arr[$elementName][] = trim($element[0]);
                } else {
                    $arr[$elementName] = trim($element[0]);
                }
            }
            $iter++;
        }
        if (!$has_children) {
            $arr['_contents'] = trim($xml[0]);
        }
        return $arr;
    }
}
