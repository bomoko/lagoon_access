<?php

namespace Drupal\fastly_streamline_access;

class FsaComms {

  const HTTP_REQUEST_TYPE_POST = 'POST';

  const HTTP_REQUEST_TYPE_POST_JSON = 'POST_JSON';

  const HTTP_REQUEST_TYPE_GET = 'GET';

  const HTTP_REQUEST_TYPE_PUT = 'PUT';

  const HTTP_REQUEST_TYPE_DELETE = 'DELETE';

  //This isn't a constant since we might want to override it at some point?
  protected $fastlyApiBase = "https://api.fastly.com";

  protected $fastlyApiKey;

  /**
   * FsaComms constructor.
   *
   * @param $fastlyApiKey
   */
  public function __construct($fastlyApiKey) {
    $this->fastlyApiKey = $fastlyApiKey;
  }

  /**
   * @param $urlFragment
   * @param array $postData
   *
   * @return mixed
   */
  public function doGet($urlFragment, $postData = []) {
    return $this->doApiCall(
      self::HTTP_REQUEST_TYPE_GET,
      $urlFragment,
      $postData
    );
  }

  /**
   * @param $urlFragment
   * @param array $postData
   *
   * @return mixed
   */
  public function doPost($urlFragment, $postData = []) {
    return $this->doApiCall(
      self::HTTP_REQUEST_TYPE_POST,
      $urlFragment,
      $postData
    );
  }

  /**
   * @param $urlFragment
   * @param array $postData
   *
   * @return mixed
   */
  public function doJsonPost($urlFragment, $postData = []) {
    return $this->doApiCall(
      self::HTTP_REQUEST_TYPE_POST_JSON,
      $urlFragment,
      $postData
    );
  }

  /**
   * @param $urlFragment
   * @param array $postData
   *
   * @return mixed
   */
  public function doDelete($urlFragment, $postData = []) {
    return $this->doApiCall(
      self::HTTP_REQUEST_TYPE_DELETE,
      $urlFragment,
      $postData
    );
  }

  /**
   * @param $urlFragment
   * @param array $postData
   *
   * @return mixed
   */
  public function doPut($urlFragment, $postData = []) {
    return $this->doApiCall(
      self::HTTP_REQUEST_TYPE_PUT,
      $urlFragment,
      $postData
    );
  }

  /**
   * @param $type
   * @param $urlFragment
   * @param array $postData
   *
   * @return mixed
   */
  public function doApiCall($type, $urlFragment, $postData = []) {
    $curl = curl_init();

    $curlopts = [
      CURLOPT_URL => $this->fastlyApiBase . $urlFragment,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => TRUE,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => $type == FsaComms::HTTP_REQUEST_TYPE_POST_JSON ? 'POST' : $type,
      CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
        sprintf('Fastly-Key: %s', $this->fastlyApiKey),
      ],
    ];

    if ($type == FsaComms::HTTP_REQUEST_TYPE_POST) {
      $curlopts[CURLOPT_POST] = 1;
      $curlopts[CURLOPT_POSTFIELDS] = http_build_query($postData);
    }

    if ($type == FsaComms::HTTP_REQUEST_TYPE_POST_JSON) {
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
      throw new \Exception(
        sprintf("Error with json decoding: " . json_last_error_msg())
      );
    }

    return $responseDecoded;
  }

}
