<?php
// API Version : 1.0.0
namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use Firebase\JWT\JWT;
use App\Controllers\BasicFunctions;

class ApiController extends ResourceController
{
    protected $BasicFunctions;

    public function __construct()
    {
        $this->BasicFunctions = new BasicFunctions();
    }

    public function Get_Gateway_Data($id)
    {

        $db = \Config\Database::connect();
        $db->connect();
        $query = $db->table('gateways')->where('serial', $id)->get();
        $gw_data = $query->getRow();
        if ($query->getNumRows() === 1) {
        $gateway_data = [
            'id'       => $gw_data->id,
            'status_data'       => $this->BasicFunctions->Convert_Status_Data($gw_data->status_data),
            'motor_data'       => $gw_data->motor_data,
            'serial'       => $gw_data->serial,
            'imsi'        => $gw_data->imsi,
            'imei'        => $gw_data->imei,
            'softversion'        => $gw_data->softversion,
            'configstat'        => $gw_data->configstat,
            'last_connection'        => $gw_data->reg_date,
        ];


        return $this->respond($gateway_data);
    }else {

        return $this->failNotFound("gateway was not found");

    }
    }

    public function Authenticate_User()
    {
        $data = $this->request->getJSON();

    
        $db = \Config\Database::connect();
        $db->connect();
        $query = $db->table('users')->where('username', $data->username)->get();
        if ($query->getNumRows() === 1) {
            $user = $query->getRow();
            $hashedPassword = $user->password;

            if (password_verify($data->password, $hashedPassword)) {
                $key = getenv('api.jwt.secret');
                $payload = [
                    'time' => time(),
                    'username' => $data->username,
                    'exp' => time() + 3600,
                ];
                $token = JWT::encode($payload, $key, 'HS256');
                return $this->respond(['user' => $data->username, 'token' => $token]);
            }else {

                return $this->failUnauthorized("username or password is incorrect");
            }
        }else {
          
            return $this->failNotFound("profile was not found");

        }

    }

    public function Create_User() {
        $data = $this->request->getJSON();
        $invitation_code = getenv('api.invitation.code');
        if ( $data->invitationcode == $invitation_code) {
            $db = \Config\Database::connect();
            $db->connect();
            $querycheck = $db->table('users')->where('username', $data->username)->get();
            if ($querycheck->getNumRows() === 1) {
                return $this->failResourceExists("Username Exist");
            }else {
            
            if ($this->BasicFunctions->checkNotEmpty($data,false)) {
                $query = $db->table('users');
                $register_data = [
                    'username'       => $data->username,
                    'password'        => password_hash($data->password,PASSWORD_DEFAULT),
                    'email'        => $data->email,
                ];
                $query->insert($register_data); 
                return $this->respond(['user' => $data->username, 'status' => "Register Complete"]);
               
            }else {
                return $this->failValidationError("incomplete data");
            }
        }
        }else {

            return $this->failUnauthorized("for register need invitation code");
        }


    }

   public function Insert_Gateway_Data() {
        $data = $this->request->getJSON();
        $db = \Config\Database::connect();
        $db->connect();

        $querycheck = $db->table('gateways')->where('serial', $data->serial)->get();
        if ($this->BasicFunctions->checkNotEmpty($data,true)) {
        if ($querycheck->getNumRows() === 1) {
            $old_status_data = $querycheck->getRow()->status_data;
            $query = $db->table('gateways')->where('serial', $data->serial);
            $gateway_data = [
                'status_data'       => $data->status_data,
                'motor_data'       => $data->motor_data,
                'serial'       => $data->serial,
                'imsi'        => $data->imsi,
                'imei'        => $data->imei,
                'softversion'        => $data->softversion,
                'configstat'        => $data->configstat,
            ];
            if ( $old_status_data != $data->status_data) {
                $alertquery = $db->table('alerts');
                $alert_gateway_data = [
                    'status_data'       => $data->status_data,
                    'motor_data'       => $data->motor_data,
                    'serial'       => $data->serial,
                ];
                $alertquery->insert($alert_gateway_data);
            }
            $query->update($gateway_data); 
            return $this->respond(['serial' => $data->serial, 'status' => "gateway data updated"]);
        }else {
            $query = $db->table('gateways');
            $gateway_data = [
                'status_data'       => $data->status_data,
                'motor_data'       => $data->motor_data,
                'serial'       => $data->serial,
                'imsi'        => $data->imsi,
                'imei'        => $data->imei,
                'softversion'        => $data->softversion,
                'configstat'        => $data->configstat,
            ];
            $query->insert($gateway_data); 
            return $this->respond(['serial' => $data->serial, 'status' => "new gateway inserted"]);
        }
    }else {

        return $this->failValidationError("incomplete data");
    }
    }


    public function Get_Gateway_Alerts() {

        $data = $this->request->getJSON();
        $db = \Config\Database::connect();
        $db->connect();
        $querycheck = $db->table('alerts')->where('serial', $data->serial)->limit(10, (intval($data->page)-1) * 10)->get();
         if ($querycheck->getNumRows() >= 1) {
            $alarms = $querycheck->getResultArray();
            foreach ($alarms as &$object) {
                $status_data = $object['status_data'];
                $result = $this->BasicFunctions->Convert_Status_Data($status_data);
                $object['status_data'] = $result;
            }
        return $this->respond(['serial' => $data->serial, 'alaram' => $alarms ]);
        }else {

            return $this->failNotFound("gateway was not found");

        }


    }

  
}
