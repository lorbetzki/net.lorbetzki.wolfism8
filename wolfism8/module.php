<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/VariableProfileHelper.php';
require_once __DIR__ . '/../libs/datapoints.php';

	class wolfism8 extends IPSModule
	{
		use VariableProfileHelper;

		public function Create()
		{
			//Never delete this line!
			parent::Create();

			//create Serversocket
			$this->RequireParent('{8062CF2B-600E-41D6-AD4B-1BA66C32D6ED}');

		}

		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();

		}
	
		public function GetConfigurationForm()
		{
			$jsonForm = json_decode(file_get_contents(__DIR__ . "/form.json"), true);
						
			return json_encode($jsonForm);
		}


		public function RequestAction($Ident, $Value)
		{
		}

		// sending data to ISM
		public function SendData($Data) {
			$this->SendDataToParent(json_encode([
				'DataID' => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}",
				'Buffer' => utf8_encode($Data),
			]));
		}
	
		// receive data from ISM
		public function ReceiveData($JSONString) {
			$data = json_decode($JSONString);
			$HEXDATA = utf8_decode($data->Buffer);
			$HEX = $this->ReadHexToArray(utf8_decode($data->Buffer));
			
			// read data, create profiles and variable and set them
			
			$this->SendDebug(__FUNCTION__, 'ReceiveData(): receive data' . $HEXDATA, 0);
	
			// wenn Mainservice - Array 10 = F0 und Subservice - Array = 06, dann sende eine Antwort. Unterbleibt eine Antwort, sendet die ISM8 die Nachricht noch 5x.
				if ($HEX[10] == "F0" and $HEX[11] == "06"){
					// send ack to ISM
					$this->SendAck($HEX);			
				}
				
				$TELEGRAM = $this->ReadTelegram($HEX);
				
				foreach($TELEGRAM as $DTP){
					
				$DTP_VALUE = $DTP['DATAPOINT_TYPE_VALUE'];
				
					if ($DTP['DATAPOINT_IPS_TYPE'] == 0 ){
						switch ($DTP['DATAPOINT_TYPE_VALUE'])
						{
							case "on";
							case  "enable" ;
							case "true" ; 
							case "close";
								$DTP_VALUE = true;
							break;
							case "off";
							case  "disable" ;
							case "false" ; 
							case "open";
								$DTP_VALUE = false;
							break;
						}
					}
				
					$DATAPOINT_TYPE = $DTP['DATAPOINT_TYPE'];
					if (! IPS_VariableProfileExists("_ISM8_$DATAPOINT_TYPE"))
					{ 
						//echo "Profil existiert nicht!";
						CreateVariableProfile($DATAPOINT_TYPE);
					}
			
					$IPS_IDENT = "DTP_".$DTP['DATAPOINT_ID'];
					$IPS_NAME = $DTP['DATAPOINT_ID']."_".$DTP['DATAPOINT_NAME'];
					$POS = 1;

					$this->MaintainVariable($IPS_IDENT, $IPS_NAME, $DTP['DATAPOINT_IPS_TYPE'], "_ISM8_".$DTP['DATAPOINT_TYPE'], $DTP['DATAPOINT_ID'], true);

					$this->SetValue($IPS_IDENT, $DTP_VALUE);

					$this->SendDebug(__FUNCTION__, 'ReadTelegram(): ' . $IPS_IDENT . $IPS_NAME , 0);

				}


		}

		private function ReadHexToArray($HEX) 
		{
			$HEX = unpack("H*" ,$HEX);
			$HEX = explode(" ", (wordwrap((strtoupper($HEX[1])), 2, " ", true)));
			$this->SendDebug(__FUNCTION__, 'ReadHexToArray(): Read HEX data to Array', 0);
			return $HEX;
		}

		private function SendAck($HEX)
		{
			$HEADER = "$HEX[0]$HEX[1]$HEX[2]$HEX[3]";
			$FRAMESIZE = "0011";
			$CONNECTHEADER = "$HEX[6]$HEX[7]$HEX[8]$HEX[9]";
			$OBJECTSERVER = "$HEX[10]86$HEX[12]$HEX[13]000000";
			$ACK = pack("H*" ,"$HEADER$FRAMESIZE$CONNECTHEADER$OBJECTSERVER");
			
			$this->SendData($ACK);			
			$this->SendDebug(__FUNCTION__, 'SendAck(): send ack to ISM.' . $ACK, 0);
			return;
		}

		public function ReloadAllData()
		{
			$HEX = pack("H*" ,"0620F080001604000000F0D0");
			$this->SendData($HEX);

			$this->LogMessage($this->Translate('ReloadAllData(): send reloaddata to ISM.'), KL_MESSAGE);
			$this->SendDebug(__FUNCTION__, 'ReloadAllData(): send reloaddata to ISM' . $HEX, 0);
			return;
		}

		// some functions to decode data from heater
		private function PdtScaling($HEX)
		{
			return round(($HEX & 0xff) * 100 / 255, 3);
		}

		private function PdtKNXFloat($HEX, $RES=0.01)
		{
			$sign = ($HEX & 0x8000) >> 15; //0x8000 = 1000 0000 0000 0000 dieses Bit ist das Vorzeichen (-)
			$exponent = ($HEX & 0x7800) >> 11; // 0x7800 = ‭0111 1000 0000 0000‬ diese bits sind für den exponeten. 
			$mantisse = $HEX & 0x07ff; // 0x07ff = ‭0000 0111 1111 1111 diese Bits stellen die Range dar‬
			if ($sign != 0) { 
				$mantisse = -(~($mantisse - 1) & 0x07ff);
				}
			$value = (1 << $exponent) * $RES * $mantisse;
			return $value;
		}

		private function PdtLong($HEX)
		{
			return ($HEX > 2147483647 and $HEX - 2147483648 or $HEX);
		}

		private function PdtTime($HEX)
		{
		$b1 = ($HEX & 0xff0000) >> 16;
		$b2 = ($HEX & 0x00ff00) >> 8;
		$b3 = ($HEX & 0x0000ff);
		$weekday = ($b1  & 0xe0) >> 5;
		//@weekdays = ["","Mo","Di","Mi","Do","Fr","Sa","So"];
		$hour = $b1 & 0x1f;
		$min = $b2 & 0x3f;
		$sec = $b3 & 0x3f;
		$value = sprintf("%s %d:%d:%d", $weekday, $hour, $min, $sec);
		return $value;
		}

		private function PdtDate($HEX)
		{
			$b1 = ($HEX & 0xff0000) >> 16;
			$b2 = ($HEX & 0x00ff00) >> 8;
			$b3 = ($HEX & 0x0000ff);
			$day = $b1 & 0x1f;
			$mon = $b2 & 0xf;
			$year = $b3 & 0x7f;

			if ($year < 90) { 
				$year += 2000; }
				
			else { 
					$year += 1900; 
			}
		$value = sprintf("%02d.%02d.%04d", $day, $mon, $year);
		return $value;
		}

		private function ReverseBit($BIT)
		{
			return strtr($BIT,[1,0]);
		}

		private function DecBin($DEC, $MAXBIT=16)
		{
			return str_pad(decbin($DEC), $MAXBIT, "0", STR_PAD_LEFT);
		}

		private function PdtKNXFloatDec($DEC, $RES=0.01)
		{
			$val = $DEC / $RES;
			$exp = 0;

			while (!($val <= 2047 && $val >= -2048 ))
			{
				$val = $val / 2;
				$exp++;
			}
			// wenn DEC negativ, dann bilde das zweier-Komplement
			if ($DEC < 0)
			{ 
				$bin = $this->DecBin(-1 * $val, 11);
				$bin = $this->ReverseBit($bin);
				$bin = decbin(bindec($bin) + 1);
				$sign = 1;
			}
			else
			{ 
				$bin = $this->DecBin($val, 11);
				$sign = 0; 
			}
			$expbin = $this->DecBin($exp, 4);
			$ret = dechex(bindec($sign.$expbin.$bin));
			return $ret;
		}

		// Sobald man diese Funktion aufruft mit den Heizungstelegram als wert, wird das ganze als array aufgelöst. 
		//            [DATAPOINT_ID] 		= 	Integer, der Wert kann dann in den Datenpunkten-Include gesucht werden
		//            [DATAPOINT_NAME] 		= 	Name des Datenpunkte
		//            [DATAPOINT_TYPE] 		= 	Datenpunkttyp
		//			  [DATAPOINT_TYPE_VALUE]=	Wert des Datenpunkttypes 
		//            [DATAPOINT_VALUE]		=	Wert des Datenpunktes 
		//			  [DATAPOINT_OUT]		=	wenn Ausgabe = Out dann gibt es einen Ausgabewert
		//		      [DATAPOINT_IN]		= 	wenn Ausgabe = In dann nimmt dieser Datenpunkt auch Daten an.
		//		      [DATAPOINT_UNIT]		=	wenn eine Ausgabe erfolgt, dann ist das die Einheit bspw % oder °C	
		//			  [DATAPOINT_IPS_TYPE ] = 0 =bool, 1 = integer , 2=Float, 3 = String

		public function ReadTelegram($HEX)
		{
			include __DIR__ . '/../libs/datapoints.php';

				$FRAMESIZE	=	"$HEX[4] $HEX[5]";
				$MAINSERVICE	=	"$HEX[10]";
				$SUBSERVICE	=	"$HEX[11]";
				$NUMBER_OF_DP	=	hexdec("$HEX[14]$HEX[15]");
				
				$DATAPOINT_POS	=	"0";
				$DATAPOINT_VALUE	=	"" ;

			if ($MAINSERVICE == "F0" and $SUBSERVICE == "06"){	
			
				for( $i=1; $i <= $NUMBER_OF_DP; $i++ ){
						
						$DATAPOINT_VALUE		=	"0";				
						$DATAPOINT_ID			=	$HEX["$DATAPOINT_POS" + 16].$HEX["$DATAPOINT_POS" + 17];
						$DATAPOINT_CMD			=	$HEX["$DATAPOINT_POS" + 18];
						$DATAPOINT_LENGTH		=	$HEX["$DATAPOINT_POS" + 19];
									
						$DATAPOINT_ID_VAL		=	hexdec("$DATAPOINT_ID");				
						$DATAPOINT_CMD_VAL		=	hexdec("$DATAPOINT_CMD");
						$DATAPOINT_LENGTH_VAL	=	hexdec("$DATAPOINT_LENGTH");
						
						for( $n=0; $n <= $DATAPOINT_LENGTH_VAL -1; $n++ ){
								$DATAPOINT_VALUE	.=	$HEX["$DATAPOINT_POS" + 20 + $n];
							}
							
						$DATAPOINT_VALUE_VAL	=	hexdec("$DATAPOINT_VALUE");
						$DATAPOINT_TYPE			=	$DP[$DATAPOINT_ID_VAL][3];	
						$DATAPOINT_NAME			=	$DP[$DATAPOINT_ID_VAL][2];
						$DATAPOINT_OUT			=	$DP[$DATAPOINT_ID_VAL][4];
						$DATAPOINT_IN			=	$DP[$DATAPOINT_ID_VAL][5];
						$DATAPOINT_UNIT			=	$DP[$DATAPOINT_ID_VAL][6];
						$DATAPOINT_TYPE_VALUE 	= 	"";
						$DATAPOINT_IPS_TYPE			= "";
										
						switch($DATAPOINT_TYPE)
						{
							case "DPT_Switch":
								if ($DATAPOINT_VALUE_VAL == 0 ){ $DATAPOINT_TYPE_VALUE = "off";}elseif($DATAPOINT_VALUE_VAL == 1){ $DATAPOINT_TYPE_VALUE = "on";}
								$DATAPOINT_IPS_TYPE = 0;
							break;
							case "DPT_Bool":
								if ($DATAPOINT_VALUE_VAL == 0 ){ $DATAPOINT_TYPE_VALUE = "false";}elseif($DATAPOINT_VALUE_VAL == 1){ $DATAPOINT_TYPE_VALUE = "true";}
								$DATAPOINT_IPS_TYPE = 0;
							break;
							case "DPT_Enable":
								if ($DATAPOINT_VALUE_VAL == 0 ){ $DATAPOINT_TYPE_VALUE = "disable";}elseif($DATAPOINT_VALUE_VAL == 1){ $DATAPOINT_TYPE_VALUE = "enable";}
								$DATAPOINT_IPS_TYPE = 0;
							break;
							case "DPT_OpenClose":
								if ($DATAPOINT_VALUE_VAL == 0 ){ $DATAPOINT_TYPE_VALUE = "open";}elseif($DATAPOINT_VALUE_VAL == 1){ $DATAPOINT_TYPE_VALUE = "close";}
								$DATAPOINT_IPS_TYPE = 0;
							break;
							case "DPT_Scaling":
								$DATAPOINT_TYPE_VALUE = $this->PdtScaling($DATAPOINT_VALUE_VAL);
								$DATAPOINT_IPS_TYPE = 2;
							break;
							case "DPT_Value_Temp":
								$DATAPOINT_TYPE_VALUE = $this->PdtKNXFloat($DATAPOINT_VALUE_VAL);
								$DATAPOINT_IPS_TYPE = 2;
							break;
							case "DPT_Value_Tempd":
								$DATAPOINT_TYPE_VALUE = $this->PdtKNXFloat($DATAPOINT_VALUE_VAL);
								$DATAPOINT_IPS_TYPE = 2;
							break;
							case "DPT_Value_Pres":
								$DATAPOINT_TYPE_VALUE = $this->PdtKNXFloat($DATAPOINT_VALUE_VAL);
								$DATAPOINT_IPS_TYPE = 2;
							break;
							case "DPT_Power":
								$DATAPOINT_TYPE_VALUE = $this->PdtKNXFloat($DATAPOINT_VALUE_VAL);
								$DATAPOINT_IPS_TYPE = 2;
							break;
							case "DPT_TimeOfDay":
							$DATAPOINT_IPS_TYPE = 3;
								//
							break;
							case "DPT_Date":
							$DATAPOINT_IPS_TYPE = 3;
								//
							break;
							case "DPT_FlowRate_m3/h":
								$DATAPOINT_IPS_TYPE = 2;
								//
							break;
							case "DPT_HVACMode":
								switch($DATAPOINT_VALUE_VAL)
								{
									case "0":
													$DATAPOINT_TYPE_VALUE = 0; //"Auto"
									break;
									case "1":
													$DATAPOINT_TYPE_VALUE = 1; //"Comfort"
									break;
									case "2":
													$DATAPOINT_TYPE_VALUE = 2; //"Standby"
									break;
									case "3":
													$DATAPOINT_TYPE_VALUE = 3; //"Economy"
									break;
									case "4":
													$DATAPOINT_TYPE_VALUE = 4; //"Building Protection"
									break;
								}
								$DATAPOINT_IPS_TYPE = 1;
							break;
							case "DPT_DHWMode":
									switch($DATAPOINT_VALUE_VAL)
									{
										case "0":
														$DATAPOINT_TYPE_VALUE = 0; //"Auto"
										break;
										case "1":
														$DATAPOINT_TYPE_VALUE = 1; //"LegioProtect"
										break;
										case "2":
														$DATAPOINT_TYPE_VALUE = 2; //"Normal"
										break;
										case "3":
														$DATAPOINT_TYPE_VALUE = 3; //"Reduced"
										break;
										case "4":
														$DATAPOINT_TYPE_VALUE = 4; //"Off/FrostProtect"
										break;
								}
								$DATAPOINT_IPS_TYPE = 1;
							break;
							case "DPT_HVACContrMode":
									switch($DATAPOINT_VALUE_VAL)
									{
										case "0":
														$DATAPOINT_TYPE_VALUE = 0; //"Auto"
										break;
										case "1":
														$DATAPOINT_TYPE_VALUE = 1; //"Heat"
										break;
										case "2":
														$DATAPOINT_TYPE_VALUE = 2; //"Morning Warmup"
										break;
										case "3":
														$DATAPOINT_TYPE_VALUE = 3; //"Cool"
										break;
										case "4":
														$DATAPOINT_TYPE_VALUE = 4; //"Night Purge"
										break;
										case "5":
														$DATAPOINT_TYPE_VALUE = 5; // "Precool"
										break;
										case "6":
														$DATAPOINT_TYPE_VALUE = 6; // "Off"
										break;
										case "7":
														$DATAPOINT_TYPE_VALUE = 7; // "Test"
										break;
										case "8":
														$DATAPOINT_TYPE_VALUE = 8; // "Emergency Heat"
										break;
										case "9":
														$DATAPOINT_TYPE_VALUE = 9; // "Fan Only"
										break;
										case "10":
														$DATAPOINT_TYPE_VALUE = 10; // "Free Cool"
										break;
										case "11":
														$DATAPOINT_TYPE_VALUE = 11; // "Ice"
										break;
										case "12":
														$DATAPOINT_TYPE_VALUE = 12; // "Maximum Heating Mode"
										break;
										case "13":
														$DATAPOINT_TYPE_VALUE = 13; //"Economic Heat/Cool Mode"
										break;
										case "14":
														$DATAPOINT_TYPE_VALUE = 14; //"Dehumidifiation"
										break;
										case "15":
														$DATAPOINT_TYPE_VALUE = 15; // "Calibration Mode"
										break;
										case "16":
														$DATAPOINT_TYPE_VALUE = 16; //"Emergency Cool Mode"
										break;
										case "17":
														$DATAPOINT_TYPE_VALUE = 17; //"Emergency Steam Mode"
										break;
										case "20":
														$DATAPOINT_TYPE_VALUE = 20; //"NoDem"
										break;	
								}
								$DATAPOINT_IPS_TYPE = 1;
							break;
							case "DPT_ActiveEnergy":
								$DATAPOINT_IPS_TYPE = 2;
								//
							break;
							case "DPT_ActiveEnergy_kWh":
								$DATAPOINT_IPS_TYPE = 2;
								//
							break;
							case "DPT_Value_Volume_Flow":
								$DATAPOINT_TYPE_VALUE = $this->PdtKNXFloat($DATAPOINT_VALUE_VAL);
								$DATAPOINT_IPS_TYPE = 2;
							break;						
													
						}
						// Die Datapoint Länge setzt sich zusammen aus Datapoint ID (2) Datapoint Kommado (1) und Länge (1)
						$DATAPOINT_POS += 4 + "$DATAPOINT_LENGTH_VAL";
						
						// Schreibe Array für die Ausgabe								
						$ReturnArray[] = array(
							"DATAPOINT_ID"				=>		"$DATAPOINT_ID_VAL",
							"DATAPOINT_NAME"		=>		"$DATAPOINT_NAME",
							"DATAPOINT_TYPE"		=>		"$DATAPOINT_TYPE",
							"DATAPOINT_TYPE_VALUE"	=>		"$DATAPOINT_TYPE_VALUE",
							"DATAPOINT_VALUE"		=>		"$DATAPOINT_VALUE_VAL",		
							"DATAPOINT_OUT"			=>		"$DATAPOINT_OUT",
							"DATAPOINT_IN"			=>		"$DATAPOINT_IN",
							"DATAPOINT_UNIT"		=>		"$DATAPOINT_UNIT",
							"DATAPOINT_IPS_TYPE"		=>		"$DATAPOINT_IPS_TYPE"							
						);	
				}
				return $ReturnArray;	
			}
		}

		private function CreateVariableProfile($DATAPOINT_TYPE)
		{
			include __DIR__ . '/../libs/datapoints.php';
						switch($DATAPOINT_TYPE)
						{
							case "DPT_Switch": // Bool (0)
								IPS_CreateVariableProfile("_ISM8_$DATAPOINT_TYPE", 0);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 1, "on", "", 0x00FF00);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 0, "off", "", 0xFF0000);
							break;
							case "DPT_Bool": // Bool (0)
								IPS_CreateVariableProfile("_ISM8_$DATAPOINT_TYPE", 0);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 1, "true", "", 0x00FF00);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 0, "false", "", 0xFF0000);
							break;
							case "DPT_Enable": // Bool (0)
								IPS_CreateVariableProfile("_ISM8_$DATAPOINT_TYPE", 0);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 1, "enable", "", 0x00FF00);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 0, "disable", "", 0xFF0000);
							break;
							case "DPT_OpenClose": // Bool (0)
								IPS_CreateVariableProfile("_ISM8_$DATAPOINT_TYPE", 0);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 1, "close", "", 0x00FF00);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 0, "open", "", 0xFF0000);
							break;
							case "DPT_Scaling": // Float (2)
								IPS_CreateVariableProfile("_ISM8_$DATAPOINT_TYPE", 2);
								IPS_SetVariableProfileText("_ISM8_$DATAPOINT_TYPE", "", "%");
								IPS_SetVariableProfileValues("_ISM8_$DATAPOINT_TYPE", "", "", "1");
							break;
							case "DPT_Value_Temp": // Float (2)
								IPS_CreateVariableProfile("_ISM8_$DATAPOINT_TYPE", 2);
								IPS_SetVariableProfileText("_ISM8_$DATAPOINT_TYPE", "", "°C");
							break;
							case "DPT_Value_Tempd": // Float (2)
								IPS_CreateVariableProfile("_ISM8_$DATAPOINT_TYPE", 2);
								IPS_SetVariableProfileText("_ISM8_$DATAPOINT_TYPE", "", "K");
							break;
							case "DPT_Value_Pres": // Float (2)
								IPS_CreateVariableProfile("_ISM8_$DATAPOINT_TYPE", 2);
								IPS_SetVariableProfileText("_ISM8_$DATAPOINT_TYPE", "", "Pa");
							break;
							case "DPT_Power": // Float (2)
								IPS_CreateVariableProfile("_ISM8_$DATAPOINT_TYPE", 2);
								IPS_SetVariableProfileText("_ISM8_$DATAPOINT_TYPE", "", "kW");
							break;
							case "DPT_TimeOfDay": // String (3)
								IPS_CreateVariableProfile("_ISM8_$DATAPOINT_TYPE", 3);
							break;
							case "DPT_Date": // String (3)
								IPS_CreateVariableProfile("_ISM8_$DATAPOINT_TYPE", 3);
							break;
							case "DPT_FlowRate_m3/h": // Float (2) 
								IPS_CreateVariableProfile("_ISM8_$DATAPOINT_TYPE", 2);
								IPS_SetVariableProfileText("_ISM8_$DATAPOINT_TYPE", "", "m3/h");
								//
							break;
							case "DPT_HVACMode": // Integer (1)
								IPS_CreateVariableProfile("_ISM8_$DATAPOINT_TYPE", 1);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 0, "Auto", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 1, "Comfort", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 2, "Standby", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 3, "Economy", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 4, "Building Protection", "", 0xFFFFFF);
							break;
							case "DPT_DHWMode": // Integer (1)
								IPS_CreateVariableProfile("_ISM8_$DATAPOINT_TYPE", 1);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 0, "Auto", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 1, "LegioProtect", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 2, "Normal", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 3, "Reduced", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 4, "Off/Frost/Protect", "", 0xFFFFFF);						
							break;
							case "DPT_HVACContrMode": // Integer (1)
								IPS_CreateVariableProfile("_ISM8_$DATAPOINT_TYPE", 1);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 0, "Auto", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 1, "Heat", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 2, "Morning Warmup", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 3, "Cool", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 4, "Night Purge", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 5, "Precool", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 6, "Off", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 7, "Test", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 8, "Emergency Heat", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 9, "Fan Only", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 10, "Free Cool", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 11, "Ice", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 12, "Maximum Heating Mode", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 13, "Economic Heat/Cool Mode", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 14, "Dehumidifiation", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 15, "Calibration Mode", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 16, "Emergency Cool Mode", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 17, "Emergency Steam Mode", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_$DATAPOINT_TYPE", 20, "NoDem", "", 0xFFFFFF);
							 break;
							case "DPT_ActiveEnergy": // Float (2)
								IPS_CreateVariableProfile("_ISM8_$DATAPOINT_TYPE", 2);
								IPS_SetVariableProfileText("_ISM8_$DATAPOINT_TYPE", "", "Wh");
							break;
							case "DPT_ActiveEnergy_kWh": // Float (2)
								IPS_CreateVariableProfile("_ISM8_$DATAPOINT_TYPE", 2);
								IPS_SetVariableProfileText("_ISM8_$DATAPOINT_TYPE", "", "kWh");
							break;
							case "DPT_Value_Volume_Flow": // Float (2)
								IPS_CreateVariableProfile("_ISM8_$DATAPOINT_TYPE", 2);
								IPS_SetVariableProfileText("_ISM8_$DATAPOINT_TYPE", "", "l/h");
							break;
							case "DPT_Value_1_Ucount": // Integer (1)
								IPS_CreateVariableProfile("_ISM8_$DATAPOINT_TYPE", 1);
								IPS_SetVariableProfileValues("_ISM8_$DATAPOINT_TYPE", 1, 255, 1);
								break;
							case "DPT_Value_2_Ucount": // Integer (1)
								IPS_CreateVariableProfile("_ISM8_$DATAPOINT_TYPE", 1);
								IPS_SetVariableProfileValues("_ISM8_$DATAPOINT_TYPE", 1, 65535, 1);
							break;																	
						}
		} 
		
		private function CreateVariableInputProfile($DATAPOINT_TYPE, $DATAPOINT_ID)
		{
			include __DIR__ . '/../libs/datapoints.php';

						switch($DATAPOINT_TYPE)
						{
							case "DPT_Switch": // Bool (0)
								// Profile für INPUT-ID 59-64/72-77/85-90/98-103/150-153/158/193/194/
								IPS_CreateVariableProfile("_ISM8_IN_$DATAPOINT_TYPE", 0);
								IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 1, "on", "", 0x00FF00);
								IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 0, "off", "", 0xFF0000);
							break;
							case "DPT_Scaling": // Float (2)
								// Profile für INPUT-ID 198/201/204/207/209
								IPS_CreateVariableProfile("_ISM8_IN_$DATAPOINT_TYPE", 2);
								IPS_SetVariableProfileText("_ISM8_IN_$DATAPOINT_TYPE", "", "%");
								IPS_SetVariableProfileValues("_ISM8_IN_$DATAPOINT_TYPE", "", "", "1");
							break;
							case "DPT_Value_Temp": // Float (2)
								// Profile für INPUT-ID 56/69/82/95/199/202205/208/210
								IPS_CreateVariableProfile("_ISM8_IN_$DATAPOINT_TYPE", 2);
								IPS_SetVariableProfileText("_ISM8_IN_$DATAPOINT_TYPE", "", "°C");
								IPS_SetVariableProfileValues("_ISM8_IN_$DATAPOINT_TYPE", "20", "80", "1");						
							break;
							case "DPT_Value_Tempd": // Float (2)
								// Profile für Input-ID 65/78/91/104
								if ($DATAPOINT_ID == 65 || $DATAPOINT_ID == 78 || $DATAPOINT_ID == 91 || $DATAPOINT_ID == 106)
								{ 
									IPS_CreateVariableProfile("_ISM8_IN_$DATAPOINT_TYPE", 2);
									IPS_SetVariableProfileText("_ISM8_IN_$DATAPOINT_TYPE", "", "K");
									IPS_SetVariableProfileValues("_ISM8_IN_$DATAPOINT_TYPE", "-4", "4", "0.5");
								}
								// Profile für Input-ID 66/79/92/105
								if ($DATAPOINT_ID == 66 || $DATAPOINT_ID == 79 || $DATAPOINT_ID == 92 || $DATAPOINT_ID == 105)
								{ 
									IPS_CreateVariableProfile("_ISM8_IN2_$DATAPOINT_TYPE", 2);
									IPS_SetVariableProfileText("_ISM8_IN2_$DATAPOINT_TYPE", "", "K");
									IPS_SetVariableProfileValues("_ISM8_IN2_$DATAPOINT_TYPE", "0", "10", "0.5");
								}
							break;
							case "DPT_HVACMode": // Integer (1)
								// Profil für INPUT-ID 149
								if ($DATAPOINT_ID == 149)
								{ 
									IPS_CreateVariableProfile("_ISM8_IN_$DATAPOINT_TYPE", 1);
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 0, "Automatikbetrieb", "", 0xFFFFFF);
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 1, "Nennlüftung", "", 0xFFFFFF);
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 3, "Economy", "", 0xFFFFFF);
								}
								// Profil für INPUT-ID 57/70/83/96
								if ($DATAPOINT_ID == 57 || $DATAPOINT_ID == 70 || $DATAPOINT_ID == 83 || $DATAPOINT_ID == 96)
								{ 
									IPS_CreateVariableProfile("_ISM8_IN2_$DATAPOINT_TYPE", 1);
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 0, "Automatikbetrieb", "", 0xFFFFFF);
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 1, "Heizbetrieb", "", 0xFFFFFF);
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 2, "Standby", "", 0xFFFFFF);
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 3, "Sparbetrieb", "", 0xFFFFFF);						
								}
							break;
							case "DPT_DHWMode": // Integer (1)
								// Profile für INPUT-ID 58/71/84/97
								IPS_CreateVariableProfile("_ISM8_IN_$DATAPOINT_TYPE", 1);
								IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 0, "Automatikbetrieb", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 2, "Dauerbetrieb", "", 0xFFFFFF);
								IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 4, "Standby", "", 0xFFFFFF);
							break;
							case "DPT_HVACContrMode": // Integer (1)
								// Profile für INPUT-ID 2/15/28/41/    ACHTUNG!!!! hinter dem Befehl steht der Wert der an die Anlage gesendet werden MUSS!!
								if ($DATAPOINT_ID == 2 || $DATAPOINT_ID == 15 || $DATAPOINT_ID == 28 || $DATAPOINT_ID == 41)
								{ 
									IPS_CreateVariableProfile("_ISM8_IN_$DATAPOINT_TYPE", 1);
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 0, "Test", "", 0xFFFFFF); // 7
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 1, "Start", "", 0xFFFFFF); // 1
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 2, "Frost Heizkreis", "", 0xFFFFFF); // 11
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 3, "Frost Warmwasser", "", 0xFFFFFF); // 11
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 4, "Schornsteinfeger", "", 0xFFFFFF); // 0
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 5, "Kombibetrieb", "", 0xFFFFFF); // 1
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 6, "Parallelbetrieb", "", 0xFFFFFF); // 1
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 7, "Warmwasserbetrieb", "", 0xFFFFFF); // 1
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 8, "Warmwassernachlauf", "", 0xFFFFFF); // 1
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 9, "Mindest-Kombizeit", "", 0xFFFFFF); // 1
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 10, "Heizbetrieb", "", 0xFFFFFF); // 1
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 11, "Nachlauf Heizkreispumpe", "", 0xFFFFFF); // 1
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 12, "Frostschutz", "", 0xFFFFFF); // 11
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 13, "Standby", "", 0xFFFFFF); // 6
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 14, "Kaskadenbetrieb", "", 0xFFFFFF); // 1
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 15, "GLT-Betrieb", "", 0xFFFFFF); // 7
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 16, "Kalibration", "", 0xFFFFFF); // 15
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 17, "Kalibration Heizbetrieb", "", 0xFFFFFF); // 15
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 18, "Kalibration Warmwasserbetrieb", "", 0xFFFFFF); // 15
									IPS_SetVariableProfileAssociation("_ISM8_IN_$DATAPOINT_TYPE", 19, "Kalibration Kombibetrieb", "", 0xFFFFFF); // 15
								}
								// Profile für INPUT-ID 178     ACHTUNG!!!! hinter dem Befehl steht der Wert der an die Anlage gesendet werden MUSS!!
								if ($DATAPOINT_ID == 178)
								{ 
									IPS_CreateVariableProfile("_ISM8_IN2_$DATAPOINT_TYPE", 1);
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 0, "ODU Test", "", 0xFFFFFF); // 7
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 1, "Test", "", 0xFFFFFF); // 7
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 2, "Frostschutz HK", "", 0xFFFFFF); // 11
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 3, "Frostschutz Warmwasser", "", 0xFFFFFF); // 11
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 4, "Durchfluss gering", "", 0xFFFFFF); // 1
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 5, "Vorwärmung", "", 0xFFFFFF); // 2
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 6, "Abtaubetrieb", "", 0xFFFFFF); // 11
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 7, "Antilegionellenfunktion", "", 0xFFFFFF); // 0
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 8, "Warmwasserbetrieb", "", 0xFFFFFF); // 1
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 9, "WW-Nachlauf", "", 0xFFFFFF); // 1
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 10, "Heizbetrieb", "", 0xFFFFFF); // 1
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 11, "HZ-Nachlauf", "", 0xFFFFFF); // 1
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 12, "Aktive Kühlung", "", 0xFFFFFF); // 3
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 13, "Kaskade", "", 0xFFFFFF); // 1
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 14, "GLT", "", 0xFFFFFF); // 7
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 15, "Standby", "", 0xFFFFFF); // 6
									IPS_SetVariableProfileAssociation("_ISM8_IN2_$DATAPOINT_TYPE", 16, "Pump down", "", 0xFFFFFF); // 3
								}
							 break;
							case "DPT_TimeOfDay": // String (3)
								// Profile für INPUT-ID 156/157/161/162
								IPS_CreateVariableProfile("_ISM8_IN_$DATAPOINT_TYPE", 3);
							break;
							case "DPT_Date": // String (3)
								// Profile für INPUT-ID 154/155/159/160
								IPS_CreateVariableProfile("_ISM8_IN_$DATAPOINT_TYPE", 3);
							break;
							case "DPT_Date": // String (3)
								// Profile für INPUT-ID 154/155/159/160
								IPS_CreateVariableProfile("_ISM8_IN_$DATAPOINT_TYPE", 3);
							break;
							
		
						}
		}
}