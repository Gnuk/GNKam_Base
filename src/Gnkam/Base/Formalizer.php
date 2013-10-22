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
			return null;
		}
		
		# Create cache group directory if not exists
		$fileDir = $this->cache . '/' . $name;
		if(!is_dir($fileDir))
		{
			if(!mkdir($fileDir))
			{
				return null;
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
				
				# Receive the group json data
				$json['data'] = call_user_func_array(array(
					$this, $functionName
				), $functionParams);
				
				# Set meta group informations
				$json['status'] = 'last';
				$json['updated'] = time();
				$json['date'] = time();
				
				# Put it in a string
				$string = json_encode($json);
				
				# Test data
				if(!empty($string) AND count($json['data']) > 0)
				{
					file_put_contents($filePath, $string);
				}
				else
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
						$json = array('error' => 'resource get failure');
					}
				}
				# Remove lock file
				unlink($filePathPending);
			}
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
	* @return array Json object in PHP array to serialize
	*/
	public function get($name, $param)
	{
		$fileDir = $this->cache . '/' . $name;
		$filePath = $fileDir . '/' . $param . '.json';
		if(!is_file($filePath))
		{
			return null;
		}
		return json_decode(file_get_contents($filePath), true);
	}
}