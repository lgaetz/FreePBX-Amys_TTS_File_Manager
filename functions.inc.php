<?php

class AmyError
{
	public static $error_queue = array();
	public static $fatal_error = FALSE;
	
	public static function AddError($message)
	{
		self::$error_queue[] = $message;
	}
}


class AmyEnvironment
{
	public static function GetWhichCommand()
	{
		return '/usr/bin/which';
	}
	
	public static function GetDependencies()
	{
		return array('sox','mpg123');
	}
	
	public static function Mp3ToWav(string $source, $delete = TRUE)
	{
		 $command = exec(self::GetWhichCommand() . ' mpg123',$output,$retval);
		 $o = exec("$command -q -w " . str_replace('.mp3','.wav',$source) . " $source");
		 
		if ($delete)
			unlink($source);		 
	}

	public static function WavToSln(string $source, $delete = TRUE)
	{
		$command = exec(self::GetWhichCommand() . ' sox',$output,$retval);
		$o = exec("$command $source -q -r 8000 -t raw " . str_replace('.wav','.sln',$source) . " tempo -s 1.3");
		
		if ($delete)
			unlink($source);
	}

}

class AmyTTSMaker
{
	public static function GetTableName()
	{
		return 'amys_tts';
	}
	
	public static function GetPathSoundLibrary()
	{
		return '/var/www/html/admin/modules/amys_ttsfilemanager/sound_library/';
	}
	
	public static function GetPathSoundProcessing()
	{
		return '/var/www/html/admin/modules/amys_ttsfilemanager/temp_soundfiles/';
	}
	
	public static function GetPathAsteriskStorage()
	{
		 return '/var/lib/asterisk/sounds/amy_tts/';
	}
	
	public static function GetLongModuleName()
	{
		return 'Amy\'s TTS File Manager';
	}
	
	public static function DependencyTest(array $dependencies)
	{
		if(PHP_SHLIB_SUFFIX != 'so')
		{
			AmyError::AddError('Sorry in beta, this only works on *nix systems. Cannot proceed.');
			return FALSE;
		}

		$error_flag = FALSE;		
		foreach($dependencies as $dependency)
		{
			$test = exec(AmyEnvironment::GetWhichCommand() . " $dependency",$output, $retval);
			if (strlen($test) < 1)
			{
				$error_flag = TRUE;
				AmyError::AddError("Unable to find /usr/sbin/$dependency on your system. Cannot proceed.");
			}
		}
		return ($error_flag ? FALSE: TRUE);
	}
	
	public static function TextSegmenter(string $text)
	{
		$broken = array();
		
		$sentences = explode('.',$text);

		foreach($sentences as $sentence)
			$broken = array_merge($broken,explode('#break#',wordwrap($sentence,90,"#break#",TRUE)));

		return $broken;
	}
	
	public static function Codec2Extension(string $codec)
	{
		$code = strtolower($codec);
		
		switch ($codec)
		{
			case 'a':	return 'sln';
						break;
		}
		
		return false;
	}

	public static function CreateTTSFile(string $text, string $filename)
	{
		$tts_files = array();
		
		$filename = self::SanitizeTTS($filename);

		$texts = self::TextSegmenter($text);

		foreach($texts as $broken)
		{
			$filehash = self::GetPathSoundProcessing() . hash('md5',microtime());
	
			$audio=file_get_contents('http://translate.google.com/translate_tts?tl=en&q={' . urlencode($broken) . '}');
			
			file_put_contents($filehash . '.mp3', $audio);
			
			$tts_files[] = array('text' => $broken, 'filehash' => $filehash, 'file_ext' => 'mp3');
		}
		
		if ( !$fh_new = fopen(self::GetPathSoundProcessing() . $filename . '.mp3',"w+b"))
		{
			AmyError::AddError('Unable to write file to disk! [' . __LINE__ . ']');
			return FALSE;
		}
		
		#Combine our partial audio files into a single file
		foreach($tts_files as $tts_file)
		{
			if (! $fh_old = fopen($tts_file['filehash'] . '.mp3',"rb"))
			{
				AmyError::AddError('Unable to read file from disk! [' . __LINE__ . ']');
				return FALSE;
			}			

			while (! feof($fh_old)) 
			{
				if (! fwrite($fh_new,fread($fh_old, 8192)))
				{
					AmyError::AddError('Unable to write audio output file! [' . __LINE__ . ']');
					return FALSE;
				}
			}			
			fclose($fh_old);						
		}
		
		#Delete all the partial audio files
		foreach($tts_files as $tts_file)
		{
			unlink($tts_file['filehash'] . '.mp3');
		}
				
		fclose($fh_new);
		
		AmyEnvironment::Mp3ToWav(self::GetPathSoundProcessing() . $filename . '.mp3',FALSE);
		AmyEnvironment::WavToSln(self::GetPathSoundProcessing() . $filename . '.wav');		
		
		copy(self::GetPathSoundProcessing() . $filename . '.sln',self::GetPathSoundLibrary() . $filename . '.sln');
		unlink(self::GetPathSoundProcessing().$filename . '.sln');

		copy(self::GetPathSoundProcessing() . $filename . '.mp3',self::GetPathSoundLibrary() . $filename . '.mp3');
		unlink(self::GetPathSoundProcessing().$filename . '.mp3');
		
		return self::GetPathSoundLibrary() . $filename . '.sln';
	}	
	
	public static function ValidateID(string $id)
	{
		if (strlen($id) != 32)
			return FALSE;
			
		if (! ctype_alnum($id))
			return FALSE;
			
		return TRUE;
	}
	
	public static function ValidateFilename(string $filename)
	{
		if (strlen($filename) < 1)
			return FALSE;
				
		if (strlen($filename) > 128)
			return FALSE;
				
		if(!ctype_alnum(str_replace(array('-','_'), '', $filename))) 
			return FALSE;					

		if (preg_match('/[[:blank:]]/',$filename))
			return FALSE;

		return TRUE;
	}	
	
	public function SanitizeFilename(string $filename)
	{
		$clean = $filename;
		$clean = preg_replace('/[[:blank:]]+/',' ',$clean);	
		$clean = str_replace(' ','',$clean);
		$clean = preg_replace('[ ]','-',$clean);
		$clean = preg_replace('/[^a-z0-9\\-\\_]/i','',$clean);
		
		return $clean;
	}
	
	public static function ValidateTTS(string $text)
	{
		if (strlen($text) < 1)
			return FALSE;
			
		if (strlen($text) > 500)
			return FALSE;
			
		if (preg_match('/[\[\]\(\)\{\}[:cntrl:]]/',$text))			
			return FALSE;
		
		return TRUE;
	}
	
	public static function SanitizeTTS(string $text)
	{
        $text = preg_replace('/[\[\]\(\)\{\}[:cntrl:]]/','',$text);
        $text = trim($text);	
		
		return $text;	
	}
	
	public static function SanitizeCodec(string $codec)
	{
		if (! array_key_exists($codec,self::GetAllowedCodecs()))
			return 'a';	

		return $codec;
	}
	
	public static function ValidateCodec(string $codec)
	{
		if (! array_key_exists($codec,self::GetAllowedCodecs()))
			return FALSE;
			
		return TRUE;
	}
	
	public static function GetAllowedCodecs()
	{
		return array(
			'a' => 'sln'
		);
	}
		
}
