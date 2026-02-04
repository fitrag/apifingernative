<?php
/**
 * ZKLib - Library untuk komunikasi dengan mesin fingerprint ZKTeco
 * Mendukung X100C dan mesin ZKTeco lainnya via protokol UDP port 4370
 */

class ZKLib
{
    private $ip;
    private $port;
    private $socket;
    private $sessionId = 0;
    private $replyId = 0;
    private $timeout = 5;
    public $lastError = '';

    // Command constants
    const CMD_CONNECT = 1000;
    const CMD_EXIT = 1001;
    const CMD_ENABLE_DEVICE = 1002;
    const CMD_DISABLE_DEVICE = 1003;
    const CMD_RESTART = 1004;
    const CMD_POWEROFF = 1005;
    const CMD_ACK_OK = 2000;
    const CMD_ACK_ERROR = 2001;
    const CMD_ACK_DATA = 2002;
    const CMD_PREPARE_DATA = 1500;
    const CMD_DATA = 1501;
    const CMD_USER_TEMP_RRQ = 9;
    const CMD_ATTLOG_RRQ = 13;
    const CMD_CLEAR_DATA = 14;
    const CMD_CLEAR_ATTLOG = 15;
    const CMD_DELETE_USER = 18;
    const CMD_DELETE_USER_TEMP = 19;
    const CMD_CLEAR_ADMIN = 20;
    const CMD_USERTEMP_WRQ = 8;
    const CMD_SET_USER_INFO = 72;
    const CMD_WRITE_LCD = 66;
    const CMD_GET_TIME = 201;
    const CMD_SET_TIME = 202;
    const CMD_VERSION = 1100;
    const CMD_DEVICE_NAME = 11;
    const CMD_GET_FREE_SIZES = 50;
    const CMD_OPTIONS_RRQ = 11;
    const CMD_OPTIONS_WRQ = 12;

    const USHRT_MAX = 65535;

    public function __construct($ip, $port = 4370)
    {
        $this->ip = $ip;
        $this->port = $port;
    }

    /**
     * Buat koneksi ke mesin
     */
    public function connect()
    {
        $this->socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$this->socket) {
            $this->lastError = 'Failed to create socket';
            return false;
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->timeout, 'usec' => 0]);

        $command = $this->createHeader(self::CMD_CONNECT, 0, 0);

        if (!@socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port)) {
            $this->lastError = 'Failed to send connect command';
            return false;
        }

        $response = $this->receiveData();
        if ($response && strlen($response) >= 8) {
            $this->sessionId = unpack('v', substr($response, 4, 2))[1];
            $this->replyId = unpack('v', substr($response, 6, 2))[1];
            return true;
        }

        $this->lastError = 'No response from device';
        return false;
    }

    /**
     * Putuskan koneksi
     */
    public function disconnect()
    {
        if ($this->socket) {
            $command = $this->createHeader(self::CMD_EXIT, $this->sessionId, $this->replyId);
            @socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port);
            @socket_close($this->socket);
            $this->socket = null;
        }
        return true;
    }

    /**
     * Enable device
     */
    public function enableDevice()
    {
        $command = $this->createHeader(self::CMD_ENABLE_DEVICE, $this->sessionId, $this->replyId);
        @socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port);
        return $this->receiveData() !== false;
    }

    /**
     * Disable device (untuk operasi write)
     */
    public function disableDevice()
    {
        $command = $this->createHeader(self::CMD_DISABLE_DEVICE, $this->sessionId, $this->replyId);
        @socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port);
        return $this->receiveData() !== false;
    }

    /**
     * Set user ke mesin menggunakan format CMD_SET_USER_INFO (lebih kompatibel)
     * @param string $uid User ID (PIN) - akan dikonversi ke integer
     * @param string $name Nama user (max 24 chars)
     * @param string $password Password (opsional, max 8 chars)
     * @param int $role Role (0=user, 14=admin)
     * @param string $cardno Card number (opsional)
     */
    public function setUser($uid, $name, $password = '', $role = 0, $cardno = '')
    {
        // Konversi UID ke integer (hapus leading zeros)
        $uidInt = intval(ltrim($uid, '0')) ?: intval($uid);
        if ($uidInt <= 0) {
            $uidInt = 1;
        }

        // Batasi panjang
        $name = substr($name, 0, 24);
        $password = substr($password, 0, 8);
        $uidStr = strval($uidInt);

        // Coba dengan CMD_SET_USER_INFO (format string-based, lebih kompatibel)
        $userData = sprintf(
            "PIN=%s\tName=%s\tPri=%d\tPasswd=%s\tCard=%s\tGrp=1\tTZ=0000000100000000\t",
            $uidStr,
            $name,
            $role,
            $password,
            $cardno
        );

        $command = $this->createHeader(self::CMD_SET_USER_INFO, $this->sessionId, $this->replyId);
        $command .= $userData . "\x00";

        @socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port);

        $response = $this->receiveData();
        if ($response && strlen($response) >= 8) {
            $cmd = unpack('v', substr($response, 0, 2))[1];
            if ($cmd == self::CMD_ACK_OK) {
                return true;
            }
            $this->lastError = "Device returned error code: $cmd";
        } else {
            $this->lastError = "No response or invalid response";
        }

        return false;
    }

    /**
     * Set user dengan format binary (alternatif)
     */
    public function setUserBinary($uid, $name, $password = '', $role = 0, $cardno = '')
    {
        $uidInt = intval(ltrim($uid, '0')) ?: intval($uid);
        if ($uidInt <= 0) $uidInt = 1;

        $name = substr($name, 0, 24);
        $password = substr($password, 0, 8);
        $cardnoInt = intval($cardno);

        // Format 72 bytes untuk CMD_USERTEMP_WRQ
        $data = pack('v', $uidInt);                     // 2 bytes: user internal id
        $data .= pack('C', $role);                      // 1 byte: role/privilege
        $data .= str_pad($password, 8, "\x00");         // 8 bytes: password
        $data .= str_pad($name, 24, "\x00");            // 24 bytes: name
        $data .= pack('V', $cardnoInt);                 // 4 bytes: card number
        $data .= pack('C', 1);                          // 1 byte: group number
        $data .= pack('v', 0);                          // 2 bytes: user timezone
        $data .= str_repeat("\x00", 8);                 // 8 bytes: timezone 1-4
        $data .= str_pad(strval($uidInt), 9, "\x00");   // 9 bytes: PIN string
        
        // Pad to 72 bytes
        while (strlen($data) < 72) {
            $data .= "\x00";
        }

        $command = $this->createHeader(self::CMD_USERTEMP_WRQ, $this->sessionId, $this->replyId);
        $command .= $data;

        @socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port);

        $response = $this->receiveData();
        if ($response && strlen($response) >= 8) {
            $cmd = unpack('v', substr($response, 0, 2))[1];
            return $cmd == self::CMD_ACK_OK;
        }

        return false;
    }

    /**
     * Hapus user dari mesin
     * @param string $uid User ID (PIN)
     */
    public function deleteUser($uid)
    {
        $uidInt = intval(ltrim($uid, '0')) ?: intval($uid);
        
        // Coba dengan format string dulu
        $data = "PIN=" . $uidInt . "\x00";
        
        $command = $this->createHeader(self::CMD_DELETE_USER, $this->sessionId, $this->replyId);
        $command .= $data;

        @socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port);

        $response = $this->receiveData();
        if ($response && strlen($response) >= 8) {
            $cmd = unpack('v', substr($response, 0, 2))[1];
            return $cmd == self::CMD_ACK_OK;
        }

        return false;
    }

    /**
     * Ambil semua user dari mesin
     */
    public function getUsers()
    {
        $command = $this->createHeader(self::CMD_USER_TEMP_RRQ, $this->sessionId, $this->replyId);
        @socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port);

        $response = $this->receiveData();
        if (!$response) {
            return [];
        }

        $users = [];
        $cmd = unpack('v', substr($response, 0, 2))[1];

        if ($cmd == self::CMD_PREPARE_DATA) {
            $size = unpack('V', substr($response, 8, 4))[1];
            $data = $this->receiveRawData($size);

            if ($data) {
                $userSize = 72;
                $count = floor(strlen($data) / $userSize);

                for ($i = 0; $i < $count; $i++) {
                    $userData = substr($data, $i * $userSize, $userSize);
                    $uid = unpack('v', substr($userData, 0, 2))[1];
                    $role = unpack('C', substr($userData, 2, 1))[1];
                    $password = rtrim(substr($userData, 3, 8), "\x00");
                    $name = rtrim(substr($userData, 11, 24), "\x00");
                    $cardno = unpack('V', substr($userData, 35, 4))[1];
                    $uidStr = rtrim(substr($userData, 48, 9), "\x00");

                    if ($uid > 0) {
                        $users[] = [
                            'uid' => $uid,
                            'uid_str' => $uidStr ?: strval($uid),
                            'name' => $name,
                            'role' => $role,
                            'password' => $password,
                            'cardno' => $cardno
                        ];
                    }
                }
            }
        }

        return $users;
    }

    /**
     * Ambil info device
     */
    public function getDeviceInfo()
    {
        $info = [];

        // Get version
        $command = $this->createHeader(self::CMD_VERSION, $this->sessionId, $this->replyId);
        @socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port);
        $response = $this->receiveData();
        if ($response && strlen($response) > 8) {
            $info['version'] = rtrim(substr($response, 8), "\x00");
        }

        return $info;
    }

    /**
     * Restart device
     */
    public function restart()
    {
        $command = $this->createHeader(self::CMD_RESTART, $this->sessionId, $this->replyId);
        @socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port);
        return true;
    }

    /**
     * Buat header paket
     */
    private function createHeader($command, $sessionId, $replyId)
    {
        $buf = pack('v', $command);
        $buf .= pack('v', 0); // checksum placeholder
        $buf .= pack('v', $sessionId);
        $buf .= pack('v', $replyId);

        // Calculate checksum
        $checksum = $this->calculateChecksum($buf);
        $buf = substr($buf, 0, 2) . pack('v', $checksum) . substr($buf, 4);

        return $buf;
    }

    /**
     * Hitung checksum
     */
    private function calculateChecksum($data)
    {
        $checksum = 0;
        $len = strlen($data);

        for ($i = 0; $i < $len; $i += 2) {
            if ($i + 1 < $len) {
                $checksum += unpack('v', substr($data, $i, 2))[1];
            } else {
                $checksum += ord($data[$i]);
            }
        }

        $checksum = ($checksum & 0xFFFF) + ($checksum >> 16);
        $checksum = ~$checksum & 0xFFFF;

        return $checksum;
    }

    /**
     * Terima data dari socket
     */
    private function receiveData()
    {
        $data = '';
        $from = '';
        $port = 0;

        $result = @socket_recvfrom($this->socket, $data, 1024, 0, $from, $port);

        if ($result === false || $result < 8) {
            return false;
        }

        $this->replyId++;
        if ($this->replyId >= self::USHRT_MAX) {
            $this->replyId = 0;
        }

        return $data;
    }

    /**
     * Terima raw data (untuk data besar)
     */
    private function receiveRawData($size)
    {
        $data = '';
        $remaining = $size;

        while ($remaining > 0) {
            $chunk = '';
            $from = '';
            $port = 0;

            $result = @socket_recvfrom($this->socket, $chunk, 65535, 0, $from, $port);

            if ($result === false) {
                break;
            }

            // Skip header (8 bytes) for data packets
            if (strlen($chunk) > 8) {
                $cmd = unpack('v', substr($chunk, 0, 2))[1];
                if ($cmd == self::CMD_DATA) {
                    $chunk = substr($chunk, 8);
                }
            }

            $data .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $data;
    }

    /**
     * Test koneksi
     */
    public function testConnection()
    {
        if ($this->connect()) {
            $info = $this->getDeviceInfo();
            $this->disconnect();
            return ['success' => true, 'info' => $info];
        }
        return ['success' => false, 'message' => $this->lastError ?: 'Tidak dapat terhubung ke mesin'];
    }
}
