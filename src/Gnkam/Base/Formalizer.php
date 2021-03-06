<?php
/*
* Copyright (c) 2013 GNKW & Kamsoft.fr
*
* This file is part of Gnkam Base.
*
* Gnkam Base is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Gnkam Base is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with Gnkam Base.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace Gnkam\Base;

/**
 * Formalizer class
 * @author Anthony Rey <anthony.rey@mailoo.org>
 * @since 30/09/2013
 */
abstract class Formalizer
{
	/**
	* Cache directory
	* @var string
	*/
	private $cache;
	
	/**
	* Know there is a cache
	* @var boolean
	*/
	private $cachingOk = false;
	
	/**
	* Update time in seconds
	* @var integer
	*/
	private $update;
	
	/**
	* Max locktime
	* @var integer;
	*/
	private $locktimeup;

	/**
	 * Formalizer constructor
	 * @param string $cache Cache directory
	 * @param integer $update Update time in seconds
	 */
	public function __construct($cache, $update)
	{
		if(is_dir($cache))
		{
			$this->cache = rtrim($cache, '/');
			$this->cachingOk = true;
		}
		$this->setUpdate($update);
	}
	
	/**
	* Change update time
	* @param integer $update Update time in seconds
	*/
	public function setUpdate($update)
	{
		$this->update = $update;
		$this->locktimeup = $this->update/2;
	}
	
	/**
	* Call the service
	* @param string $name Name of the function calling data (example : 'name' call 'nameGroup' and the file produced is in 'name' cache directory)
	* @param string|integer $param Callback parameter and filename (example : 3 will call nameGroup(3))
	* @param array $functionParams If you want custom parameter(s) or no parameters (example : array(3,2) will call nameGroup(3,2))
	* @return array Json object in PHP array to serialize
	*/
	protected function service($name, $param, $functionParams = null)
	{
		# Function reconstitution
		$functionName = $name.'Data';
		if(null === $functionParams)
		{
			$functionParams = array(
				$param
			);
		}
		
		# Check for cache
		if(!$this->cachingOk)
		{
			return array(
				'type' => 'error',
				'message' => 'Cache directory problem',
				'code' => 500
			);
		}
		
		# Create cache group directory if not exists
		$fileDir = $this->cache . '/' . $name;
		if(!is_dir($fileDir))
		{
			if(!mkdir($fileDir))
			{
				return array(
					'type' => 'error',
					'message' => 'Impossible to create cache',
					'code' => 500
				);
			}
		}
		
		# Files to create
		$filePath = $fileDir . '/' . $param . '.json';
		$filePathPending = $filePath . '.lock';
		
		# Initialisation
		$json = array();
		$recreate = false;
		
		# Test pending
		$pending = $this->testPending($filePathPending);

		# File already exist
		if(is_file($filePath))
		{
			$json = json_decode(file_get_contents($filePath), true);
			if($pending)
			{
				$json['status'] = 'pending';
			}
			else
			{
				if(isset($json['updated']))
				{
					$updateTimeMax = $json['updated'] + $this->update;
					if(time() > $updateTimeMax)
					{
						$recreate = true;
					}
				}
				else
				{
					$recreate = true;
				}
			}
		}
		else
		{
			$recreate = true;
		}
		
		# Recreate file
		if($recreate)
		{
			if($pending AND is_file($filePath))
			{
				$json = json_decode(file_get_contents($filePath), true);
				$json['status'] = 'pending';
			}
			else
			{
				# Create lock file
				file_put_contents($filePathPending, time());
				
				$receivedData = call_user_func_array(array(
					$this, $functionName
				), $functionParams);
				# Test return
				if($receivedData === null)
				{
					$json = $this->treatAsError($filePath,
						array(
							'type' => 'error',
							'message' => 'Resource get failure',
							'code' => 500
						)
					);
				}
				else if($receivedData instanceof ReceiverError)
				{
					$json = $this->treatAsError($filePath,
						array(
							'type' => 'error',
							'message' => $receivedData->getMessage(),
							'code' => $receivedData->getCode()
						)
					);
				}
				else
				{
					# Receive the group json data
					$json['data'] = $receivedData;
					
					# Set meta group informations
					$json['status'] = 'last';
					$json['updated'] = time();
					$json['date'] = time();
					
					# Put it in a string
					$string = json_encode($json);
					
					
					
					# Test data
					if(!empty($string))
					{
						file_put_contents($filePath, $string);
					}
					else
					{
						$json = $this->treatAsError($filePath,
							array(
								'type' => 'error',
								'message' => 'Resource get failure',
								'code' => 500
							)
						);
					}
					
				}
				# Remove lock file
				unlink($filePathPending);
			}
		}
		return $json;
	}
	
	private function treatAsError($filePath, $error)
	{
		# Error case (example : impossible to contact ADE)
		if(is_file($filePath))
		{
			# Old file exist : send old file
			$json = json_decode(file_get_contents($filePath), true);
			$json['status'] = 'old';
			$json['updated'] = time() - $this->locktimeup;
			$string = json_encode($json);
			file_put_contents($filePath, $string);
		}
		else
		{
			# Send error
			$json = $error;
		}
		return $json;
	}
	
	/**
	* Test if service is lockeb by another call
	* @param string $file_Lockfile path
	*/
	private function testPending($file)
	{
		$currentTime = time();
		if(is_file($file))
		{
			$lockTimeMax = file_get_contents($file) + $this->locktimeup;
			if($currentTime > $lockTimeMax)
			{
				unlink($file);
			}
			else
			{
				return true;
			}
		}
		return false;
	}
	
	/**
	* Get a service
	* @param string $name Name of the resource directory
	* @param string|integer $param Resource parameter
	* @param boolean $meta If there is metainformations
	* @return array Json object in PHP array to serialize
	*/
	public function get($name, $param, $meta = false)
	{
		$fileDir = $this->cache . '/' . $name;
		$filePath = $fileDir . '/' . $param . '.json';
		if(!is_file($filePath))
		{
			return null;
		}
		$object = json_decode(file_get_contents($filePath), true);
		if($meta)
		{
			return $object;
		}
		return $object['data'];
	}
	
	/**
	* Get cache link
	* @return string Cache link
	*/
	public function getCacheLink()
	{
		return $this->cache;
	}
}