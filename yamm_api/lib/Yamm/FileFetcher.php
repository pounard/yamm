<?php
// $Id$

/**
 * Fetcher could not fetch the new file.
 */
class Yamm_FileFetcher_CouldNotFetchException extends Yamm_EntityException {}

/**
 * Fetcher could not save the new file.
 */
class Yamm_FileFetcher_CouldNotSaveException extends Yamm_EntityException {}

/**
 * Default file fetcher interface.
 */
interface Yamm_FileFetcherInterface
{
  /**
   * Fetch file from server.
   * 
   * @param object $file
   *   Drupal core file database row. Fid will be ignored in all cases. This
   *   object will be modified and will contain the final new file path.
   * @param boolean $replace = FALSE
   *   (optional) If set to TRUE, and filepath is set, the existing file will
   *   be replaced.
   * 
   * @throws Yamm_FileFetcher_CouldNotFetchException
   *   If file could not be fetched.
   * @throws Yamm_FileFetcher_CouldNotSaveException
   *   If file coulnd not be saved.
   */
  public function fetch($file, $dest = 0, $replace = FALSE);
}

/**
 * Default file fetcher.
 */
abstract class Yamm_FileFetcher implements Yamm_FileFetcherInterface
{
  /**
   * (non-PHPdoc)
   * @see Yamm_FileFetcherInterface::fetch()
   */
	public function fetch($file, $dest = 0, $replace = FALSE) {
		// Fetch the real file as a temporary file.
		$src = $this->_fetch($file->filepath);
		// Create the new file destination. Use the $replace boolean to compute a
		// new file name if the the user asked for no file replace.
		$dest = file_destination(file_create_path($dest) . '/' . $file->filename, ($replace ? FILE_EXISTS_REPLACE : FILE_EXISTS_RENAME));
		// Copy the temporary file as the real file.
		$error = !file_copy($src, $file->filepath, ($replace ? FILE_EXISTS_REPLACE : FILE_EXISTS_ERROR));
		// Throw our exception in case of any error.
		if ($error) {
			throw new Yamm_FileFetcher_CouldNotSaveException("File " . $file->filepath . " could not copied.");
		}
		// In all case, remove the temporary file. Be silent here, whatever happens.
		// In case of unlink failure, only put a warning message in watchdog.
		if (!@unlink($src)) {
			watchdog('yamm', "File " . $src . " could not be deleted", NULL, WATCHDOG_ERROR);
		}
		// Set the new filepath to our file structure.
		$file->filepath = $dest;
	}

	/**
	 * Specific, implementation dependent server file fetch. The file must be
	 * saved in a temporary directory.
	 * 
	 * In case of any internal error, the temporary file must be removed.
	 * 
	 * @param string $filepath
	 *   Server side filepath.
	 * 
	 * @return string
	 *   Temporary file filepath.
	 */
	public abstract function _fetch($filepath);
}
