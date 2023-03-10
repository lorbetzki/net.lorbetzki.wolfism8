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

			
			// set actions for these variable
			$this->RegisterPropertyBoolean('DTP_57', false);
			$this->RegisterPropertyBoolean('DTP_65', false);
			$this->RegisterPropertyBoolean('DTP_194', false);

			//create Properties to disable errormessage
			$this->RegisterPropertyBoolean('DTP_1', false);
			$this->RegisterPropertyBoolean('DTP_14', false);
			$this->RegisterPropertyBoolean('DTP_27', false);
			$this->RegisterPropertyBoolean('DTP_40', false);
			$this->RegisterPropertyBoolean('DTP_53', false);
			$this->RegisterPropertyBoolean('DTP_106', false);
			$this->RegisterPropertyBoolean('DTP_114', false);
			$this->RegisterPropertyBoolean('DTP_121', false);
			$this->RegisterPropertyBoolean('DTP_128', false);
			$this->RegisterPropertyBoolean('DTP_135', false);
			$this->RegisterPropertyBoolean('DTP_148', false);
			
			$this->ForceParent('{8062CF2B-600E-41D6-AD4B-1BA66C32D6ED}');
		}

		public function Destroy()
		{
			//Never delete this line!
			if (!IPS_InstanceExists($this->InstanceID)) {
				$Profile = IPS_GetVariableProfileList();

				foreach($Profile as $key =>$value) {
					$ISM_Profile = strpos($value,"ISM_");
						if ($ISM_Profile === 0)
						{
							$this->UnregisterProfile("$value");
						}
				}
			}	
			parent::Destroy();
		}
		
		/*
		public function GetConfigurationForParent() 
		{
			return json_encode(['Active' => true]);
		}
		*/

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();

			// Bei der Ersteinrichtung werden Störungsvariablen vom System empfangen obwohl entsprechende DatenID nicht existieren. Dies kann hiermit abgeschaltet werden.
			$DTPVAR = array(1,14,27,40,53,106,114,121,128,135,148);
			foreach($DTPVAR as $DTP){		

				$DTP_IDENT="DTP_".$DTP;
				if  ($this->ReadPropertyBoolean($DTP_IDENT))
				{ 
					$this->UnregisterVariable($DTP_IDENT);
				}
			}
			
			$DTPVARWRITE = array(57,65,194);
			foreach($DTPVARWRITE as $DTP){

				$DTP_IDENT="DTP_".$DTP;
				if  ($this->ReadPropertyBoolean($DTP_IDENT))
				{ 
					$this->EnableAction($DTP_IDENT);	
				}
			}

			if ($this->HasActiveParent())
			{
				ISM_ReloadAllData($_IPS['TARGET']);
			}
		}
	
		public function GetConfigurationForm()
		{
			$jsonForm = json_decode(file_get_contents(__DIR__ . "/form.json"), true);

			// prüfe ob DTP 57, 65 oder 194 als Variable angelegt wurde und bietet den User an, zu der Variable eine "Action" hinzuzufügen.
			if  (@$this->GetIDForIdent('DTP_57'))
			{
				$jsonForm["elements"][1]["visible"] = true;
			}
			if  (@$this->GetIDForIdent('DTP_65'))
			{
				$jsonForm["elements"][2]["visible"] = true;
			}
			if  (@$this->GetIDForIdent('DTP_194'))
			{
				$jsonForm["elements"][3]["visible"] = true;
			}
			
			return json_encode($jsonForm);
		}


		public function RequestAction($Ident, $Value)
		{
			$this->LogMessage("RequestAction : $Ident, $Value",KL_NOTIFY);

			switch($Ident)
			{
				case 'DTP_65':
					$this->SetPoint($Value);
					$this->SetValue($Ident,$Value);

				break;
				case 'DTP_57': //Betriebsstatus
					$this->SetOperatingMode($Value);
					$this->SetValue($Ident,$Value);
				break;
				case 'DTP_194': // 1x Warmwasser Aufbereitung
					$PREP_TELEGRAM = pack("H*" ,"0620F080001504000000F0C100C2000100C2000101");
					$this->SendData($PREP_TELEGRAM);
					$this->SetValue($Ident, true);
				break;
			}
		
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
					if (! IPS_VariableProfileExists("ISM_$DATAPOINT_TYPE"))
					{ 
						//echo "Profil existiert nicht!";
						$this->CreateVariableProfile($DATAPOINT_TYPE);
					}
			
					$IPS_IDENT = "DTP_".$DTP['DATAPOINT_ID'];
					$IPS_NAME = $DTP['DATAPOINT_ID']."_".$DTP['DATAPOINT_NAME'];
					$POS = 1;
					$CREATEVAR = true;

					// Bei der Ersteinrichtung werden Störungsvariablen vom System empfangen obwohl entsprechende DatenID nicht existieren. Dies kann hiermit abgeschaltet werden.
					if ( ($IPS_IDENT == "DTP_1") || ($IPS_IDENT == "DTP_14") || ($IPS_IDENT == "DTP_27") || ($IPS_IDENT == "DTP_40") || ($IPS_IDENT == "DTP_53") || 
					($IPS_IDENT == "DTP_106") || ($IPS_IDENT == "DTP_114") || ($IPS_IDENT == "DTP_121") || ($IPS_IDENT == "DTP_128") || ($IPS_IDENT == "DTP_135") || ($IPS_IDENT == "DTP_148")) 
					{
						if  ($this->ReadPropertyBoolean($IPS_IDENT)){ $CREATEVAR = false; }
					}
					$this->MaintainVariable($IPS_IDENT, $IPS_NAME, $DTP['DATAPOINT_IPS_TYPE'], "ISM_".$DTP['DATAPOINT_TYPE'], $DTP['DATAPOINT_ID'], $CREATEVAR);
					$this->SetValue($IPS_IDENT, $DTP_VALUE);

					// bei Schreibbaren Idents diese Sichtbar machen.
					if ( ($IPS_IDENT == "DTP_57") || ($IPS_IDENT == "DTP_65") || ($IPS_IDENT == "DTP_194") )
					{
						$this->UpdateFormField("$IPS_IDENT", "visible", true);
						$this->SendDebug(__FUNCTION__, 'EnableWrite: ' . $IPS_IDENT , 0);
					}
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

		private function PdtUcount1($HEX)
		{
			return ($HEX & 0xff);
		}
		
		private function PdtUcount2($HEX)
		{
			return ($HEX & 0xffff);
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

		private function ReadTelegram($HEX)
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
							case "DPT_Value_Tempd_IN":
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
							case "DPT_Value_1_Ucount":
								$DATAPOINT_TYPE_VALUE = $this->PdtUcount1($DATAPOINT_VALUE_VAL);
								$DATAPOINT_IPS_TYPE = 1;
							break;
							case "DPT_Value_2_Ucount":
								$DATAPOINT_TYPE_VALUE = $this->PdtUcount2($DATAPOINT_VALUE_VAL);
								$DATAPOINT_IPS_TYPE = 1;
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
						$this->RegisterProfileBooleanEx("ISM_$DATAPOINT_TYPE",'','','', [
							 [1, 'on', '', 0x00FF00],
							 [0, 'off', '', 0xFF0000]
						]);
					break;
					case "DPT_Bool": // Bool (0)
						$this->RegisterProfileBooleanEx("ISM_$DATAPOINT_TYPE",'','','', [
							[1, 'true', '', 0x00FF00],
							[0, 'false', '', 0xFF0000]
					   ]);
					break;
					case "DPT_Enable": // Bool (0)
						$this->RegisterProfileBooleanEx("ISM_$DATAPOINT_TYPE",'','','', [
							[1, 'enable', '', 0x00FF00],
							[0, 'disable', '', 0xFF0000]
					   ]);
					break;
					case "DPT_OpenClose": // Bool (0)
						$this->RegisterProfileBooleanEx("ISM_$DATAPOINT_TYPE",'','','', [
							[1, 'close', '', 0x00FF00],
							[0, 'open', '', 0xFF0000]
					   ]);
					break;
					case "DPT_Scaling": // Float (2)
						//    protected function RegisterProfileFloat($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits)

						$this->RegisterProfileFloat("ISM_$DATAPOINT_TYPE", '', '', ' %', 0, 100, 0, 1);
					break;
					case "DPT_Value_Temp": // Float (2)
						
						$this->RegisterProfileFloat("ISM_$DATAPOINT_TYPE", '', '', ' °C', -273, 670760, 0.01, 1);
					break;
					case "DPT_Value_Tempd": // Float (2)
						$this->RegisterProfileFloat("ISM_$DATAPOINT_TYPE", '', '', ' K', -670760, 670760, 0.01, 1);
					break;
					case "DPT_Value_Pres": // Float (2)
						$this->RegisterProfileFloat("ISM_$DATAPOINT_TYPE", '', '', ' Pa', -670760, 670760, 0.01, 1);
					break;
					case "DPT_Power": // Float (2)
						$this->RegisterProfileFloat("ISM_$DATAPOINT_TYPE", '', '', ' kW', -670760, 670760, 0.01, 1);
					break;
					case "DPT_TimeOfDay": // String (3)
						IPS_CreateVariableProfile("ISM_$DATAPOINT_TYPE", 3);
					break;
					case "DPT_Date": // String (3)
						IPS_CreateVariableProfile("ISM_$DATAPOINT_TYPE", 3);
					break;
					case "DPT_FlowRate_m3/h": // Float (2) 
						$this->RegisterProfileFloat("ISM_$DATAPOINT_TYPE", '', '', ' m3/h', -2147483647, 2147483647, 0.0001, 1);
					break;
					case "DPT_HVACMode": // Integer (1)
						$this->RegisterProfileIntegerEx("ISM_$DATAPOINT_TYPE", '', '', '', [
							[0, "Auto", "", 0xFFFFFF ],
							[1, "Comfort/Heizbetrieb", "", 0xFFFFFF],
							[2, "Standby", "", 0xFFFFFF],
							[3, "Economy/Sparbetrieb", "", 0xFFFFFF],
							[4, "Building Protection", "", 0xFFFFFF]
						]);
					break;
					case "DPT_DHWMode": // Integer (1)
						$this->RegisterProfileIntegerEx("ISM_$DATAPOINT_TYPE", '', '', '', [
							[0, "Auto", "", 0xFFFFFF ],
							[1, "LegioProtect", "", 0xFFFFFF],
							[2, "Normal", "", 0xFFFFFF],
							[3, "Reduced", "", 0xFFFFFF],
							[4, "Off/Frost/Protect", "", 0xFFFFFF]
						]);
					break;
					case "DPT_HVACContrMode": // Integer (1)
						$this->RegisterProfileIntegerEx("ISM_$DATAPOINT_TYPE", '', '', '', [
							[0, "Auto", "", 0xFFFFFF ],
							[1, "Heat", "", 0xFFFFFF],
							[2, "Morning Warmup", "", 0xFFFFFF],
							[3, "Cool", "", 0xFFFFFF],
							[4, "Night Purge", "", 0xFFFFFF],
							[5, "Precool", "", 0xFFFFFF],
							[6, "Off", "", 0xFFFFFF],
							[7, "Test", "", 0xFFFFFF],
							[8, "Emergency Heat", "", 0xFFFFFF],
							[9, "Fan Only", "", 0xFFFFFF],
							[10, "Free Cool", "", 0xFFFFFF],
							[11, "Ice", "", 0xFFFFFF],
							[12, "Maximum Heating Mode", "", 0xFFFFFF],
							[13, "Economic Heat/Cool Mode", "", 0xFFFFFF],
							[14, "Dehumidifiation", "", 0xFFFFFF],
							[15, "Calibration Mode", "", 0xFFFFFF],
							[16, "Emergency Cool Mode", "", 0xFFFFFF],
							[17, "Emergency Steam Mode", "", 0xFFFFFF],
							[20, "NoDem", "", 0xFFFFFF]
						]);
						break;
					case "DPT_ActiveEnergy": // Float (2)
						$this->RegisterProfileFloat("ISM_$DATAPOINT_TYPE", '', '', ' Wh', -2147483647, 2147483647, 1, 1);
					break;
					case "DPT_ActiveEnergy_kWh": // Float (2)
						$this->RegisterProfileFloat("ISM_$DATAPOINT_TYPE", '', '', ' kWh', -2147483647, 2147483647, 1, 1);
					break;
					case "DPT_Value_Volume_Flow": // Float (2)
						$this->RegisterProfileFloat("ISM_$DATAPOINT_TYPE", '', '', ' l/h', -670760, 670760, 0.1, 1);
					break;
					case "DPT_Value_1_Ucount": // Integer (1)
						$this->RegisterProfileInteger("ISM_$DATAPOINT_TYPE", '', '', '', 0, 255, 1);
					break;
					case "DPT_Value_2_Ucount": // Integer (1)
						$this->RegisterProfileInteger("ISM_$DATAPOINT_TYPE", '', '', '', 0, 65535, 1);
					break;	
					case "DPT_Value_Tempd_IN": // Float (2)
						$this->RegisterProfileFloat("ISM_$DATAPOINT_TYPE", '', '', ' K', -4, 4, 0.5, 1);
					break;																
				}

				
		} 

		private function SetPoint($SetPointValue)
		{
			switch ($SetPointValue)
			{
				case -4:
						$PREP_TELEGRAM = "8670";
				break;
				case -3.5:
						$PREP_TELEGRAM = "86A2";
				break;
				case -3:
						$PREP_TELEGRAM = "86D4";
				break;

				case -2.5:
						$PREP_TELEGRAM = "8706";
				break;

				case -2:
						$PREP_TELEGRAM = "8738";
				break;

				case -1.5:
						$PREP_TELEGRAM = "876A";
				break;

				case -1:
						$PREP_TELEGRAM = "879C";
				break;

				case -0.5:
						$PREP_TELEGRAM = "87CE";
				break;
				case 0:
						$PREP_TELEGRAM = "0000";
				break;

				case 0.5:
						$PREP_TELEGRAM = "0032";
				break;

				case 1:
						$PREP_TELEGRAM = "0064";
				break;

				case 1.5:
						$PREP_TELEGRAM = "0096";
				break;

				case 2:
						$PREP_TELEGRAM = "00C8";
				break;

				case 2.5:
						$PREP_TELEGRAM = "00FA";
				break;

				case 3:
						$PREP_TELEGRAM = "012C";
				break;

				case 3.5:
						$PREP_TELEGRAM = "015E";
				break;
				case 4:
						$PREP_TELEGRAM = "0190";
				break;
			}

			$HEADER = "0620F080001604000000F0C10041000100410302";
			$TELEGRAM = pack("H*" ,$HEADER.$PREP_TELEGRAM);
			$this->SendData($TELEGRAM);			
			$this->SendDebug(__FUNCTION__, 'SetPoint(): send Setpoint to ISM.' . $SetPointValue, 0);
		}

		// for DPT ID 57 Programmwahl Heizkreis
		private function SetOperatingMode($ONOFF)
		{
			switch ($ONOFF)
			{
				case 2: // heater off
					$PREP_TELEGRAM = pack("H*" ,"0620F080001504000000F0C1003900010039000102");
				break;
				case 1: //heater Comfort
					$PREP_TELEGRAM = pack("H*" ,"0620F080001504000000F0C1003900010039000101");
				break;
				case 0: //heater Auto
					$PREP_TELEGRAM = pack("H*" ,"0620F080001504000000F0C1003900010039000100");
				break;
				case 3: //heater Economy
					$PREP_TELEGRAM = pack("H*" ,"0620F080001504000000F0C1003900010039000103");
				break;
				case 4: //heater Building protection
					$PREP_TELEGRAM = pack("H*" ,"0620F080001504000000F0C1003900010039000104");
				break;
			}
		
			$this->SendData($PREP_TELEGRAM);			
			$this->SendDebug(__FUNCTION__, 'SetOperatingMode(): set mode to ISM ' . $ONOFF, 0);
		}
}