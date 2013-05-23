<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//Copyright (C) 2013 Planet IDX, Inc. (amygrant@planetidx.com)
//
//Written by Amy Grant <amygrant@planetidx.com>.
//
//This program is free software; you can redistribute it and/or
//modify it under the terms of the GNU General Public License
//as published by the Free Software Foundation; either version 2
//of the License, or (at your option) any later version.
//
//This program is distributed in the hope that it will be useful,
//but WITHOUT ANY WARRANTY; without even the implied warranty of
//MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//GNU General Public License for more details.
$amy_action = isset($_GET['action']) && $_GET['action'] == 'delete' ? 'delete' : (isset($_GET['action']) && $_GET['action'] == 'edit' ? 'edit' : (isset($_POST['action']) ? $_POST['action'] : 'add'));

if (AmyTTSMaker::DependencyTest(AmyEnvironment::GetDependencies()) !== TRUE)
	AmyError::$fatal_error = TRUE;

if (! AmyError::$fatal_error)
{
	if ($amy_action == 'delete')
	{
		$amy_id = $_GET['id'];
		if (AmyTTSMaker::ValidateID($amy_id))
		{
			$amy_file_profile = sql('SELECT * FROM `' . AmyTTSMaker::GetTableName() . '` WHERE id="' . mysql_real_escape_string($amy_id) . '"','getrow', DB_FETCHMODE_ASSOC);
			$amy_file_profile['extension'] = AmyTTSMaker::Codec2Extension($amy_file_profile['codec']);

			$sql = 'DELETE FROM `' . AmyTTSMaker::GetTableName() . '` WHERE id ="' . mysql_real_escape_string($amy_id) . '"';
			sql($sql);

			if (! unlink(AmyTTSMaker::GetPathSoundLibrary() . $amy_file_profile['filename'] . '.' . $amy_file_profile['extension']))
				AmyError::AddError('<p>ERROR: Unable to delete file ' . AmyTTSMaker::GetPathSoundLibrary() . $amy_file_profile['filename'] . '.' . $amy_file_profile['extension'] . '</p>');
		
			if (! unlink(AmyTTSMaker::GetPathSoundLibrary() . $amy_file_profile['filename'] . '.mp3'))	
				AmyError::AddError('<p>ERROR: Unable to delete file ' . AmyTTSMaker::GetPathSoundLibrary() . $amy_file_profile['filename'] . '.mp3</p>');
							
			echo '<p>TTS file deleted</p>';
		}
		else
			AmyError::AddError('<p>ERROR: Invalid file id. Nothing deleted</p>');

		$amy_action = 'add';
	}
	
	if ($amy_action == 'edit')
	{
		$amy_id = isset($_POST['id']) ? $_POST['id'] : $_GET['id'];
		
		if (AmyTTSMaker::ValidateID($amy_id))
		{		
			$amy_file_profile = sql('SELECT * FROM `' . AmyTTSMaker::GetTableName() . '` WHERE id="' . mysql_real_escape_string($amy_id) . '"','getrow', DB_FETCHMODE_ASSOC);
			$amy_file_profile['extension'] = AmyTTSMaker::Codec2Extension($amy_file_profile['codec']);

			$amy_output .= "<a href='config.php?display=amys_ttsfilemanager&action=delete&id=" . $amy_id . "'><img src='images/core_delete.png' /> Delete Sound File</a>";
			
			if (isset($_POST['amy_submit']))
			{
				$amy_new_filename = AmyTTSMaker::SanitizeFilename($_POST['amy_filename']);				
				if ($amy_file_profile['filename'] != $amy_new_filename)
				{
					if (AmyTTSMaker::ValidateFilename($amy_new_filename))					
					{					
						if (! file_exists(AmyTTSMaker::GetPathSoundLibrary() . $amy_new_filename . '.' . $amy_file_profile['extension']))					
						{							
							$sql = 'UPDATE `' . AmyTTSMaker::GetTableName() . '` set filename="' . mysql_real_escape_string($amy_new_filename) . '" WHERE id="' . $amy_file_profile['id'] . '"';
							sql($sql);
							
							copy(AmyTTSMaker::GetPathSoundLibrary() . $amy_file_profile['filename'] . '.' . $amy_file_profile['extension'], AmyTTSMaker::GetPathSoundLibrary() . $amy_new_filename . '.' . $amy_file_profile['extension']);
							if (! unlink(AmyTTSMaker::GetPathSoundLibrary() . $amy_file_profile['filename'] . '.' . $amy_file_profile['extension']))
								AmyError::AddError('<p>ERROR: Unable to delete file ' . AmyTTSMaker::GetPathSoundLibrary() . $amy_file_profile['filename'] . '.' . $amy_file_profile['extension'] . '</p>');						
							
							copy(AmyTTSMaker::GetPathSoundLibrary() . $amy_file_profile['filename'] . '.mp3' , AmyTTSMaker::GetPathSoundLibrary() . $amy_new_filename . '.mp3' );
							if (! unlink(AmyTTSMaker::GetPathSoundLibrary() . $amy_file_profile['filename'] . '.mp3'))
								AmyError::AddError('<p>ERROR: Unable to delete file ' . AmyTTSMaker::GetPathSoundLibrary() . $amy_file_profile['filename'] . '.mp3</p>');
						}
						else
							AmyError::AddError('<p>ERROR: Cannot rename TTS file because another TTS file with that name already exists.<br />Either choose a different name or delete the other file first.</p>');
					}
					else						
						AmyError::AddError('<p>ERROR: Invalid failename.</p>');						
				}
				
				$amy_new_tts = AmyTTSMaker::SanitizeTTS($_POST['amy_tts']);
				if ($amy_file_profile['tts'] != $amy_new_tts)
				{				
					if (AmyTTSMaker::ValidateTTS($amy_new_tts))
					{
						$sql = 'UPDATE `' . AmyTTSMaker::GetTableName() . '` set tts="' . mysql_real_escape_string($amy_new_tts) . '" WHERE id="' . $amy_file_profile['id'] . '"';
						sql($sql);
						
						if (($tts_file = AmyTTSMaker::CreateTTSFile($amy_new_tts,$amy_file_profile['filename'])) == FALSE)
						{}
					}
					else
						AmyError::AddError('<p>ERROR: Invalid TTS text.</p>');					
				}

				if ($_POST['amy_export'] == 'checked')
				{
					copy(AmyTTSMaker::GetPathSoundLibrary() . $amy_file_profile['filename'] . '.' . $amy_file_profile['extension'], AmyTTSMaker::GetPathAsteriskStorage() . $amy_file_profile['filename'] . '.' . $amy_file_profile['extension'] );

					sql('INSERT INTO recordings (displayname, filename, description) VALUES ( "' . mysql_real_escape_string($amy_file_profile['filename']) . '", "amy_tts' . DIRECTORY_SEPARATOR . mysql_real_escape_string($amy_file_profile['filename']) . '", "No long description available")');
					
					echo '<p>File has been copied as a system recording. You can access by going to the <a href="config.php?display=recordings">System Recordings module</a></p>';
				}
			}

			$amy_file_profile = sql('SELECT * FROM `' . AmyTTSMaker::GetTableName() . '` WHERE id="' . mysql_real_escape_string($amy_id) . '"','getrow', DB_FETCHMODE_ASSOC);
			$amy_file_profile['extension'] = AmyTTSMaker::Codec2Extension($amy_file_profile['codec']);

			$amy_output .= '<form method="post" action="config.php?display=amys_ttsfilemanager">';
			$amy_output .= '<input type="hidden" name="action" value="edit" />';
			$amy_output .= '<input type="hidden" name="id" value="' . $amy_file_profile['id'] . '" />';
			$amy_output .= '<input type="hidden" name="amy_codec" value="' . $amy_file_profile['codec'] . '" />';			
			$amy_output .= '<table>';
			
			$amy_output .=	'<tr>';
			$amy_output .=	'<td colspan="2"><h5>Edit a TTS Sound File</h5><hr></td>';
			$amy_output .=	'</tr>';
			
			$amy_output .= '<tr>';
			$amy_output .= '<td><a href="#" class="info">' . _("File Location") . '<span>' . _("This is what you put into your asterisk files") . '</span></a></td>';
			$amy_output .= '<td>';
			$amy_output .= AmyTTSMaker::GetPathSoundLibrary() . $amy_file_profile['filename'] . '.' . $amy_file_profile['extension'];
			$amy_output .= '</td>';
			$amy_output .= '</tr>';
			
			$amy_output .=	'<tr>';
			$amy_output .=	'<td><a href="#" class="info">' . _("Name of Recording") . '<span>' . _("This is used to determine the filename. Do not include a file extension.<br />If left blank, I will automatically create a name using the first few words of your text") . '</span></a></td>';
			$amy_output .=	'<td><input type="text" name="amy_filename" value="' . $amy_file_profile['filename'] . '" /></td>';
			$amy_output .=	'</tr>';
						
			$amy_output .= '<tr>';
			$amy_output .= '<td><a href="#" class="info">' . _("What To Say") . '<span>' . _("The text you want convert too speech") . '</span></a></td>';
			$amy_output .= '<td>';
			$amy_output .= '<textarea name="amy_tts">' . $amy_file_profile['tts'] . ' </textarea>';
			$amy_output .= '</td>';
			$amy_output .= '</tr>';
			
			$amy_output .= '<tr>';
			$amy_output .= '<td><a href="#" class="info">' . _("Export as System Recording") . '<span>' . _("Should I make a copy of this file and put it with other system built-in recordings?") . '</span></a></td>';
			$amy_output .= '<td>';
			$amy_output .= '<input type="checkbox" name="amy_export" value="checked" />';
			$amy_output .= '</td>';
			$amy_output .= '</tr>';	
			
			$amy_output .= '<tr>';
			$amy_output .= '<td colspan="2">';
			$amy_output .= '<input type="submit" name="amy_submit" value="Update" />';
			$amy_output .= '</td>';
			$amy_output .= '</tr>';
			
			
			$amy_output .= '</table>';
			$amy_output .= '</form>';

			$amy_output .= '<audio controls="controls" ><source src="modules/amys_ttsfilemanager/sound_library/' . $amy_file_profile['filename'] . '.mp3" type="audio/mp3" />Your browser does not support the audio tag.</audio>';
		}
		else
			AmyError::AddError('<p>ERROR: Invalid file id. Cannot edit</p>');					
	}
	
	if ($amy_action == 'add')	
	{
		if (isset($_POST['amy_submit']))
		{
			$amy_filename = AmyTTSMaker::SanitizeFilename($_POST['amy_filename']);
			$amy_tts = AmyTTSMaker::SanitizeTTS($_POST['amy_tts']);
			$amy_codec = AmyTTSMaker::SanitizeCodec($_POST['amy_codec']);
						
			$amy_flag = FALSE;
			if (! AmyTTSMaker::ValidateFilename($amy_filename))
			{
				$amy_flag = TRUE;
				AmyError::AddError('<p>ERROR: Invalid failename.</p>');
			}
			else
			{
				if (file_exists(AmyTTSMaker::GetPathSoundLibrary() . $amy_filename . '.' . AmyTTSMaker::Codec2Extension($amy_codec)))
				{
					$amy_flag = TRUE;
					$amy_output .= '<p>ERROR: Cannot create TTS file because another TTS file with that name already exists.<br />Either choose a different name or delete the other file first.</p>';				
				}
			}
				
			if (! AmyTTSMaker::ValidateTTS($amy_tts))
			{
				$amy_flag = TRUE;
				AmyError::AddError('<p>ERROR: Invalid TTS text.</p>');
			}
			
			if (! AmyTTSMaker::ValidateCodec($amy_codec))
			{
				$amy_flag = TRUE;
				AmyError::AddError('<p>ERROR: Invalid audio codec.</p>');
			}	
								
			if (! $amy_flag)	
			{
				if (($tts_file = AmyTTSMaker::CreateTTSFile($amy_tts,$amy_filename,$amy_codec)) !== FALSE)
				{	
					$sql = 'INSERT INTO `' . AmyTTSMaker::GetTableName() . '` (`id`, `codec`, `filename`, `tts`) VALUES ("' . mysql_real_escape_string(hash('md5',microtime())) . '", "' . mysql_real_escape_string($amy_codec) . '", "' . mysql_real_escape_string(basename($tts_file,'.' . pathinfo($tts_file,PATHINFO_EXTENSION))) . '", "' . mysql_real_escape_string($amy_tts) . '")';
					sql($sql);
					
					$amy_output .= '<p>TTS file created.</p>';
					
					unset($amy_filename);
					unset($amy_tts);
					unset($amy_codec);
				}
			}
		}

		$amy_output .= '<form method="post" action="config.php?display=amys_ttsfilemanager">';
		$amy_output .= '<input type="hidden" name ="action" value="add" />';
		$amy_output .= '<input type="hidden" name ="amy_codec" value="' . (isset($amy_codec) ? $amy_codec : '') . '" />';		
		$amy_output .= '<table>';
		
		$amy_output .=	'<tr>';
		$amy_output .=	'<td colspan="2"><h5>Create New TTS Sound File</h5><hr></td>';
		$amy_output .=	'</tr>';
		
		$amy_output .=	'<tr>';
		$amy_output .=	'<td><a href="#" class="info">' . _("Name of Recording") . '<span>' . _("This is used to determine the filename. Do not include a file extension.<br />If left blank, I will automatically create a name using the first few words of your text") . '</span></a></td>';
		$amy_output .=	'<td><input type="text" name="amy_filename" value="' . (isset($amy_filename) ? $amy_filename : '') . '" /></td>';
		$amy_output .=	'</tr>';
					
		$amy_output .= '<tr>';
		$amy_output .= '<td><a href="#" class="info">' . _("What To Say") . '<span>' . _("The text you want convert too speech") . '</span></a></td>';
		$amy_output .= '<td>';
		$amy_output .= '<textarea name="amy_tts">' . (isset($amy_tts) ? $amy_tts : '') . ' </textarea>';		
		$amy_output .= '</td>';
		$amy_output .= '</tr>';
		
		$amy_output .= '<tr>';
		$amy_output .= '<td colspan="2">';
		$amy_output .= '<input type="submit" name="amy_submit" value="Submit" />';
		$amy_output .= '</td>';
		$amy_output .= '</tr>';
		
		
		$amy_output .= '</table>';
		$amy_output .= '</form>';	
	}
}
	
$sql = 'SELECT * FROM `' . AmyTTSMaker::GetTableName() . '`';
$amy_files = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);

echo '<div class="rnav"><ul><a href="config.php?display=amys_ttsfilemanager">Create TTS Sound File</a><br /><hr />';
for($i = 0; $i < count($amy_files); $i++)
{
	echo '<li><a href="config.php?display=amys_ttsfilemanager&action=edit&id=' . $amy_files[$i]['id'] . '">' . $amy_files[$i]['filename'] . '</a></li>';	
}
echo '</ul></div>';

echo '<h2>' . AmyTTSMaker::GetLongModuleName() . '</h2>';

if (count(AmyError::$error_queue) > 0)
{
	echo '<h3>ERROR:</h3>';
	foreach(AmyError::$error_queue as $temp)
		echo "<p>$temp</p>";		
}

echo $amy_output;





