<?php
/*
* 2007-2013 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2013 PrestaShop SA
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class UploaderCore
{
	const DEFAULT_MAX_SIZE = 10485760;

	private $_accept_types;
	private $_files;
	private $_max_size;
	private $_name;
	private $_save_path;

	public function __construct($name = null)
	{
		$this->setName($name);
	}

	public function setAcceptTypes($value)
	{
		$this->_accept_types = $value;
		return $this;
	}

	public function getAcceptTypes()
	{
		if (!isset($this->_accept_types))
			$this->setAcceptTypes('/.+$/i');

		return $this->_accept_types;
	}

	public function getFiles()
	{
		if (!isset($this->_files))
			$this->_files = array();

		return $this->_files;
	}

	public function setMaxSize($value)
	{
		$this->_max_size = intval($value);
		return $this;
	}

	public function getMaxSize()
	{
		if (!isset($this->_max_size))
			$this->setMaxSize(self::DEFAULT_MAX_SIZE);

		return $this->_max_size;
	}

	public function setName($value)
	{
		$this->_name = $value;
		return $this;
	}

	public function getName()
	{
		return $this->_name;
	}

	public function setSavePath($value)
	{
		$this->_save_path = $value;
		return $this;
	}

	public function getSavePath()
	{
		if (!isset($this->_save_path))
			$this->setSavePath(_PS_UPLOAD_DIR_);

		return $this->_normalizeDirectory($this->_save_path);
	}

	public function getUniqueFileName()
	{
		return uniqid('', true);
	}

	public function process()
	{
		$this->files = array();
		$upload = isset($_FILES[$this->getName()]) ? $_FILES[$this->getName()] : null;

		if ($upload && is_array($upload['tmp_name']))
			foreach ($upload['tmp_name'] as $index => $value)
				$this->files[] = $this->upload(
					$upload['tmp_name'][$index],
					$upload['name'][$index],
					$upload['size'][$index],
					$upload['type'][$index],
					$upload['error'][$index]
				);
		else
			$this->files[] = $this->upload(
				$upload['tmp_name'],
				$upload['name'],
				isset($upload['size']) ? $upload['size'] : $this->_getServerVars('CONTENT_LENGTH'),
				isset($upload['type']) ? $upload['type'] : $this->_getServerVars('CONTENT_TYPE'),
				isset($upload['error']) ? $upload['error'] : null
			);

		return $this->files;
	}

	public function upload($tmp_name, $name, $size, $type, $error)
	{
		$file = new stdClass();
		$file->name = $name; //TODO: add unique file name if name is null
		$file->size = intval($size);
		$file->type = $type;

		if ($this->validate($tmp_name, $file, $error))
		{
			$file_path = $this->getSavePath().$file->name;
		 
			if ($tmp_name && is_uploaded_file($tmp_name)) {
					move_uploaded_file($tmp_name, $file_path);
			 } else {
				// Non-multipart uploads (PUT method support)
				file_put_contents($file_path, fopen('php://input', 'r'));
			}
			
			$file_size = $this->_getFileSize($file_path);

			if ($file_size === $file->size)
			{
				//TODO do image processing
			}
			else
			{
				$file->size = $file_size;
				unlink($file_path);
				$file->error = 'abort';
			}
		}
		
		return $file;
	}

	protected function validate($tmp_name, $file, $error)
	{
		if ($error)
		{
			$file->error = Tools::displayError($error);
			return false;
		}

		$post_max_size = $this->_getPostMaxSizeBytes();

		if ($post_max_size && ($this->_getServerVars('CONTENT_LENGTH') > $post_max_size))
		{
			$file->error = Tools::displayError('The uploaded file exceeds the post_max_size directive in php.ini');
			return false;
		}

		if (!preg_match($this->getAcceptTypes(), $file->name))
		{
			$file->error = Tools::displayError('Filetype not allowed');
			return false;
		}

		if ($file->size > $this->getMaxSize())
		{
			$file->error = Tools::displayError('File is too big');
			return false;
		}

		return true;
	}

	private function _getFileSize($file_path, $clear_stat_cache = false) {
		if ($clear_stat_cache)
			clearstatcache(true, $file_path);

		return filesize($file_path);
	}

	private function _getPostMaxSizeBytes() {
		$post_max_size = ini_get('post_max_size');
		$bytes         = trim($post_max_size);
		$last          = strtolower($post_max_size[strlen($post_max_size) - 1]);

		switch ($last)
		{
			case 'g': $bytes *= 1024;
			case 'm': $bytes *= 1024;
			case 'k': $bytes *= 1024;
		}

		return $bytes;
	}

	private function _getServerVars($var)
	{
		return (isset($_SERVER[$var]) ? $_SERVER[$var] : '');
	}

	protected function _normalizeDirectory($directory)
	{
		$last = $directory[strlen($directory) - 1];
		
		if (in_array($last, array('/', '\\'))) {
			$directory[strlen($directory) - 1] = DIRECTORY_SEPARATOR;
			return $directory;
		}
		
		$directory .= DIRECTORY_SEPARATOR;
		return $directory;
	}
}