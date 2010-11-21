<?php
// $Id$

/**
 * Simple HTTP file fetcher.
 */
class Yamm_FileFetcher_Http extends Yamm_FileFetcher
{
  /**
   * @var Yamm_Server
   */
  protected $_server;

  /**
   * Default constructor. This particular implementation needs to know the
   * server itself in order to be able to fetch files.
   */
  public function __construct(Yamm_Server $server) {
    $this->_server = $server;
  }

  /**
   * (non-PHPdoc)
   * @see Yamm_FileFetcher::_fetch()
   */
  public function _fetch($filepath) {
    $source_url = yamm_api_clean_url($this->_server->getUrl()) . $filepath;
    
    // Get file contents.
    if ($data = file_get_contents($source_url, FILE_BINARY)) {

      // Get a new temporary file name, for file creation.
      $tmp = file_directory_temp() . '/' . uniqid('yamm-');

      // If we go some content, return new file path as data.
      if (file_put_contents($tmp, $data) > 0) {
        // Free up some memory after copy.
        unset($data);
        return $tmp;
      }

      // Could not write the new temporary file.
      else {
        throw new Yamm_FileFetcher_CouldNotFetchException("Unable to save " . $source_url . " downloaded file as temporary file");
      }
    }

    // Could not download the source file.
    else {
      throw new Yamm_FileFetcher_CouldNotFetchException("Unable to download file " . $source_url);
    }
  }
}
