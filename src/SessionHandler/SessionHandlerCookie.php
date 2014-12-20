<?php

namespace SessionHandler;

class Cookie implements SessionHandlerInterface {

  private $data      = array();
  private $save_path = null;

  private $hash_len;
  private $hash_algo;
  private $hash_secret;

  public function __construct($hash_secret=null, $hash_len=128, $hash_algo="sha512") {

    $this->hash_len  = $hash_len;
    $this->hash_algo = $hash_algo;

    if (empty($hash_secret)) {
      $hash_secret = md5(php_uname() . getmypid());
    }

    $this->hash_secret = $hash_secret;
  }

  public function open($save_path, $name) {
    $this->save_path = $save_path;
    return true;
  }

  public function read($id) {

    // Check for the existance of a cookie with the name of the session id
    // Make sure that the cookie is atleast the size of our hash, otherwise it's invalid
    // Return an empty string if it's invalid.
    if (! isset($_COOKIE[$id])) return '';

    // We expect the cookie to be base64 encoded, so let's decode it and make sure
    // that the cookie, at a minimum, is longer than our expact hash length. 
    $raw = base64_decode($_COOKIE[$id]);
    if (strlen($raw) < $this->hash_len) return '';

    // The cookie data contains the actual data w/ the hash concatonated to the end,
    // since the hash is a fixed length, we can extract the last HMAC_LENGTH chars
    // to get the hash.
    $hash = substr($raw, strlen($raw)-$this->hash_len, $this->hash_len);
    $data = substr($raw, 0, -($this->hash_len));

    // Calculate what the hash should be, based on the data. If the data has not been
    // tampered with, $hash and $hash_calculated will be the same
    $hash_calculated = hash_hmac($this->hash_algo, $data, $this->hash_secret);

    // If we calculate a different hash, we can't trust the data. Return an empty string.
    if ($hash_calculated !== $hash) return '';

    // Return the data, now that it's been verified.
    return (string)$data;

  }

  public function write($id, $data) {

    // Calculate a hash for the data and append it to the end of the data string
    $hash = hash_hmac($this->hash_algo, $data, $this->hash_secret);
    $data .= $hash;

    // Set a cookie with the data
    setcookie($id, base64_encode($data), time()+3600);
  }

  public function destroy($id) {
    setcookie($id, '', time());
  }

  // In the context of cookies, these two methods are unneccessary, but must
  // be implemented as part of the SessionHandlerInterface.
  public function gc($maxlifetime) {}
  public function close() {}

}
