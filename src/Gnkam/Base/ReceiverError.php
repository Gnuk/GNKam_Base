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

class ReceiverError
{
	private $message;
	private $code;
	
	public function __construct(){
		$this->message = "";
		$this->code = 404;
	}
	
	public function define($message, $code = 404)
	{
		$this->message = $message;
		$this->code = $code;
		return $this;
	}
	
	public function setMessage($message)
	{
		$this->message = $message;
	}
	
	public function setCode($code)
	{
		$this->code = $code;
	}
	
	public function getMessage()
	{
		return $this->message;
	}
	
	public function getCode()
	{
		return $this->code;
	}
}