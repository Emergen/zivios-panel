<?php
/**
 * Copyright (c) 2008-2010 Zivios, LLC.
 *
 * This file is part of Zivios.
 *
 * Zivios is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Zivios is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Zivios.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package     Zivios
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Zivios_ValidateForm
{
    protected $form;
    public    $err = false, $errMsg = null, $cleanValues = array(), $fullFieldnames = array();
    private   $checkType, $validateKey, $fieldName, $fieldFullname, $regexLib,
              $validateKeyRegex;

    public function __construct($formData)
    {
        if (!is_array($formData) || empty($formData)) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $this->form = $formData;
        $this->regexLib = Zivios_Util::getRegexLibrary();

        foreach ($this->form as $key => $val) {
            $comps = split('_', $key);

            if (sizeof($comps) < 4) {
                Zivios_Log::error('Invalid field name structure in key: ' . $key);
                throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_STRUCTURE_INVALID'));
            }
            
            // set required params
            $this->checkType     = $comps[0];
            $this->validateKey   = $comps[1];
            $this->fieldName     = $comps[2];
            $this->fieldFullname = preg_replace('/\+/', ' ', $comps[3]);
            $this->fieldValue    = $this->removeTags($val);
            
            // check if value is coming in encoded.
            if (isset($comps[4]) && $comps[4] == 'enc') {
                $this->fieldValue = urldecode($this->fieldValue);
            }
            
            // validate field
            if (!$this->validateField()) {
                Zivios_Log::debug('Error validating form field: ' . $key . '. Breaking loop');
                return false;
            } else {
                // update cleanValues array with key: 'form fieldName' value: 'fieldValue'. The public array
                // is available on form return for easy data access. 
                // array 'fullFieldnames' holds key: 'fieldName' against value: 'fieldFullname'
                $this->cleanValues[$this->fieldName]    = $this->fieldValue;
                $this->fullFieldnames[$this->fieldName] = $this->fieldFullname;
            }
        }

        return true;
    }

    private function validateField()
    {
        if (is_array($this->fieldValue)) {
    		return true;
        }
    	
        switch ($this->checkType) {
            case "rq": case "op": 
                $this->checkValidateKey();
                if (!$this->validateValue()) {
                    $this->err    = true;
                    $this->errMsg = 'Invalid value supplied for: ' . $this->fieldFullname;
                    return false;
                }
                
                break;

            case "cmp": 
                // get the form field we need to perform a compare against, as well as 
                // form field value. 
                if (!$this->compareValues()) {
                    $this->err = true;
                    return false;
                }

                break;
        }

        return true;
    }

    private function validateValue()
    {
        // pass validation for optional fields with empty values.
        if ($this->checkType == 'op') {
            if ($this->fieldValue == '') {
                return true;
            }
        }

        if (!preg_match('/'.$this->validateKeyRegex.'/', $this->fieldValue, $matches)) {
            Zivios_Log::error('Validation failed for field: ' . $this->fieldName . 
                ' with value: ' . $this->fieldValue . ' against regex ' . $this->validateKeyRegex);
            return false;
        } else {


            Zivios_Log::debug('field: ' . $this->fieldName . ' with value: ' . $this->fieldValue . ' passed regex: ' . 
                $this->validateKeyRegex);
        }

        return true;
    }

    private function compareValues()
    {
        if ($this->checkType != 'cmp') {
            throw new Zivios_Exception(Zivios_Errorlib::errorCode(FORM_ILLEGAL_COMPARE));
        }
        
        if (array_key_exists($this->validateKey, $this->cleanValues)) {
            if ($this->fieldValue != $this->cleanValues[$this->validateKey]) {
                $this->errMsg = $this->fieldFullname . ' value does not match ' . $this->fullFieldnames[$this->validateKey];
                return false;
            } else {
                return true;
            }
        } else {
            //   Q: why have this here? can make it a requirement to "follow" compare against fields with "compare with" fields.
            //   A: form layout may dictate otherwise and display element requirements should _never_ be enforced in this manner.
            // 
            // Search for compare against field -- the field we need to compare against does not exist in 
            // existing formData arrays. This is probably because it has not been checked as yet.
            // @note: comparing against that field and having an invalid value in the field we are comparing
            // against is not an issue -- form validation will automatically fail when the field is (finally) checked.

            $pattern = '_' . $this->validateKey . '_';
            
            foreach ($this->form as $key => $val) {
                if (preg_match('/'. $pattern . '/', $key)) {
                    $tmpData = split('_', $key);

                    // preg_match would match the field being tested as well, hence, we ensure that 'validateKey' is not
                    // the value of the fieldName (which is where the match is possible).
                    if ($tmpData[2] != $this->fieldName) {
                        // value found. run compare.
                        if ($this->fieldValue != $val) {
                            $this->errMsg = $this->fieldFullname . ' value does not match ' . preg_replace('/\+/', ' ', $tmpData[3]);
                            return false;
                        } else {
                            // values match, return true
                            return true;
                        }
                    }
                } else {
                    throw new Zivios_Exception(Zivios_Errorlib::errorCode('FORM_COMPARE_KEY_MISSING'));
                }
            }
        }
        
        return false;
    }

    private function checkValidateKey()
    {
        $validateKey = $this->validateKey;
        if (!isset($this->regexLib->exp->$validateKey)) {
            Zivios_Log::error('Invalid regex key supplied for field: ' . $this->fieldName);
            throw new Zivios_Exception(Zivios_Errorlib::errorCode('FORM_REGEX_INVALID'));
        } else {
            $this->validateKeyRegex = $this->regexLib->exp->$validateKey;
        }
    }

    private function removeTags($val)
    {
    	if (is_array($val)) {
    		return $val;
    	} else {
    		return strip_tags(trim($val));
    	}
    }
}

