<?php

namespace Larabookir\Gateway\Alfacoins;


/**
 * Class ALFAcoins_publicAPI
 */
class ALFAcoins_publicAPI {
  /**
   * @var string ALFAcoins API URL
   */
  private $url = 'https://www.alfacoins.com/api';

  /**
   * Get rate for all available pairs
   * @return array
   * @throws ALFAcoins_Exception
   */
  public function rates() {
    $result = $this->getRequest("rates");
    if (!empty($result['error']))
      throw new ALFAcoins_Exception("Invalid rates result, error: " . $result['error']);
    return $result;
  }

  /**
   * Get rate for pair
   * @param string $from Rate from currency
   * @param string $to Rate to currency
   * @return array
   * @throws ALFAcoins_Exception
   */
  public function rate($from, $to) {
    $result = $this->getRequest("rate/" . $from . '_' . $to);
    if (!empty($result['error']))
      throw new ALFAcoins_Exception("Invalid rate result, error: " . $result['error']);
    return $result;
  }

  /**
   * Get all gate fees for deposit and withdrawal
   * @return array
   * @throws ALFAcoins_Exception
   */
  public function fees() {
    $result = $this->getRequest("fees");
    if (!empty($result['error']))
      throw new ALFAcoins_Exception("Invalid fees result, error: " . $result['error']);
    return $result;
  }

  /**
   * @param string $method
   * @return array
   * @throws ALFAcoins_Exception
   */
  private function getRequest($method) {
    $curl = curl_init();
    $url = $this->url . '/' . $method . '.json';
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, FALSE);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ["Content-type: application/json; charset=UTF-8"]);
    $output = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($status != 200) {
      throw new ALFAcoins_Exception("Error: call to URL $url failed with status $status, response $output, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
    }
    curl_close($curl);
    $response = json_decode($output, TRUE);
    return $response;
  }
}
