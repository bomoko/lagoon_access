<?php

namespace OpsFs;

class OpsFsComms {

  const HTTP_REQUEST_TYPE_POST = 'POST';

  const HTTP_REQUEST_TYPE_POST_JSON = 'POST_JSON';

  const HTTP_REQUEST_TYPE_GET = 'GET';

  const HTTP_REQUEST_TYPE_PUT = 'PUT';

  const HTTP_REQUEST_TYPE_DELETE = 'DELETE';

  protected $fastlyApiKey;

  public function __construct($fastlyApiKey) {
    $this->fastlyApiKey = $fastlyApiKey;
  }

  public function doGet($urlFragment, $postData = []) {
    return $this->doApiCall(self::HTTP_REQUEST_TYPE_GET, $urlFragment, $postData);
  }

  public function doPost($urlFragment, $postData = []) {
    return $this->doApiCall(self::HTTP_REQUEST_TYPE_POST, $urlFragment, $postData);
  }

  public function doJsonPost($urlFragment, $postData = []) {
    return $this->doApiCall(self::HTTP_REQUEST_TYPE_POST_JSON, $urlFragment, $postData);
  }

  public function doDelete($urlFragment, $postData = []) {
    return $this->doApiCall(self::HTTP_REQUEST_TYPE_DELETE, $urlFragment, $postData);
  }

  public function doPut($urlFragment, $postData = []) {
    return $this->doApiCall(self::HTTP_REQUEST_TYPE_PUT, $urlFragment, $postData);
  }

  public function doApiCall($type, $urlFragment, $postData = []) {

    $curl = curl_init();

    $curlopts = [
      CURLOPT_URL => $urlFragment,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => TRUE,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => $type == OpsFsComms::HTTP_REQUEST_TYPE_POST_JSON ? 'POST' : $type,
      CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
        sprintf('Fastly-Key: %s', $this->fastlyApiKey),
      ],
    ];

    if ($type == OpsFsComms::HTTP_REQUEST_TYPE_POST) {
      $curlopts[CURLOPT_POST] = 1;
      $curlopts[CURLOPT_POSTFIELDS] = http_build_query($postData);
    }

    if ($type == OpsFsComms::HTTP_REQUEST_TYPE_POST_JSON) {
      $curlopts[CURLOPT_POSTFIELDS] = json_encode($postData, JSON_FORCE_OBJECT);
      $curlopts[CURLOPT_HTTPHEADER] = [
        'Content-Type: application/json',
        'Accept: application/json',
        sprintf('Fastly-Key: %s', $this->fastlyApiKey),
      ];
    }

    curl_setopt_array($curl, $curlopts);
    $response = curl_exec($curl);
    curl_close($curl);


    $responseDecoded = json_decode($response);
    if (json_last_error()) {
      throw new Exception(sprintf("Error with json decoding: " . json_last_error_msg()));
    }

    return $responseDecoded;
  }

}