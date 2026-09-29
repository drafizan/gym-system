#!/usr/bin/env python3
"""Local Dahua NetSDK bridge for MACS.

This bridge intentionally stays small: Laravel posts a door command to this
local HTTP service, and the service calls the Dahua NetSDK bundled with
SmartPSS Lite/ConfigTool. It is designed for offline LAN use.
"""

from __future__ import annotations

import ctypes
import json
import os
from datetime import datetime
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from typing import Any


SDK_PATHS = [
    "/Applications/SmartPSSLite.app/Contents/Frameworks/libdhnetsdk.so",
    "/Applications/ConfigTool.app/Contents/Resources/libs/libdhnetsdk.so",
]

NET_RECORD_ACCESSCTLCARD = 4
NET_RECORD_ACCESSCTLCARDREC_EX = 16
CTRLTYPE_CTRL_RECORDSET_INSERT = 256

NET_ACCESSCTLCARD_STATE_NORMAL = 0
NET_ACCESSCTLCARD_STATE_LOGOFF = 0x02
NET_ACCESSCTLCARD_TYPE_GENERAL = 0

NET_EM_ACCESS_CTL_CARD_SERVICE_INSERT = 0
NET_EM_ACCESS_CTL_CARD_SERVICE_UPDATE = 2
NET_EM_ACCESS_CTL_CARD_SERVICE_REMOVE = 3
NET_EM_ACCESS_CTL_USER_SERVICE_INSERT = 0
NET_EM_ACCESS_CTL_USER_SERVICE_REMOVE = 2

NET_MAX_CARDNO_LEN = 32
NET_MAX_USERID_LEN = 32
NET_MAX_CARDPWD_LEN = 64
NET_MAX_DOOR_NUM = 32
NET_MAX_TIMESECTION_NUM = 32
MAX_ACCESSDOOR_NUM = 128
MAX_ACCESS_FLOOR_NUM = 64
MAX_ROOMNUM_COUNT = 32
MAX_COMMON_STRING_8 = 8
MAX_COMMON_STRING_16 = 16
MAX_COMMON_STRING_32 = 32
NET_COMMON_STRING_32 = 32
MAX_ORDER_NUMBER = 6
MAX_PATH = 260
EM_RECORD_ACCESSCTLCARDREC_ORDER_FIELD_CREATETIME = 2
EM_RECORD_ORDER_TYPE_DESCENT = 2


class NetTime(ctypes.Structure):
    _fields_ = [
        ("dwYear", ctypes.c_uint),
        ("dwMonth", ctypes.c_uint),
        ("dwDay", ctypes.c_uint),
        ("dwHour", ctypes.c_uint),
        ("dwMinute", ctypes.c_uint),
        ("dwSecond", ctypes.c_uint),
    ]


class NetDeviceInfoEx(ctypes.Structure):
    _fields_ = [
        ("sSerialNumber", ctypes.c_ubyte * 48),
        ("nAlarmInPortNum", ctypes.c_int),
        ("nAlarmOutPortNum", ctypes.c_int),
        ("nDiskNum", ctypes.c_int),
        ("nDVRType", ctypes.c_int),
        ("nChanNum", ctypes.c_int),
        ("byLimitLoginTime", ctypes.c_ubyte),
        ("byLeftLogTimes", ctypes.c_ubyte),
        ("bReserved", ctypes.c_ubyte * 2),
        ("nLockLeftTime", ctypes.c_int),
        ("Reserved", ctypes.c_ubyte * 24),
    ]


class NetRecordsetAccessCtlCard(ctypes.Structure):
    """Compact AccessControlCard record used by ASI standalone devices.

    The SDK record includes many newer biometric/visitor fields after this
    block. For card-only ASI1201E-D sync we send the documented core fields
    and pass this structure size as dwSize/nBufLen.
    """

    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("nRecNo", ctypes.c_int),
        ("stuCreateTime", NetTime),
        ("szCardNo", ctypes.c_char * NET_MAX_CARDNO_LEN),
        ("szUserID", ctypes.c_char * NET_MAX_USERID_LEN),
        ("emStatus", ctypes.c_int),
        ("emType", ctypes.c_int),
        ("szPsw", ctypes.c_char * NET_MAX_CARDPWD_LEN),
        ("nDoorNum", ctypes.c_int),
        ("sznDoors", ctypes.c_int * NET_MAX_DOOR_NUM),
        ("nTimeSectionNum", ctypes.c_int),
        ("sznTimeSectionNo", ctypes.c_int * NET_MAX_TIMESECTION_NUM),
        ("nUserTime", ctypes.c_int),
        ("stuValidStartTime", NetTime),
        ("stuValidEndTime", NetTime),
        ("bIsValid", ctypes.c_int),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class FindRecordAccessCtlCardRecOrder(ctypes.Structure):
    _fields_ = [
        ("emField", ctypes.c_int),
        ("emOrderType", ctypes.c_int),
        ("byReverse", ctypes.c_ubyte * 64),
    ]


class FindRecordAccessCtlCardRecConditionEx(ctypes.Structure):
    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("bCardNoEnable", ctypes.c_int),
        ("szCardNo", ctypes.c_char * NET_MAX_CARDNO_LEN),
        ("bTimeEnable", ctypes.c_int),
        ("stStartTime", NetTime),
        ("stEndTime", NetTime),
        ("nOrderNum", ctypes.c_int),
        ("stuOrders", FindRecordAccessCtlCardRecOrder * MAX_ORDER_NUMBER),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class NetInFindRecordParam(ctypes.Structure):
    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("emType", ctypes.c_int),
        ("pQueryCondition", ctypes.c_void_p),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class NetOutFindRecordParam(ctypes.Structure):
    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("lFindeHandle", ctypes.c_longlong),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class NetInFindNextRecordParam(ctypes.Structure):
    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("lFindeHandle", ctypes.c_longlong),
        ("nFileCount", ctypes.c_int),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class NetOutFindNextRecordParam(ctypes.Structure):
    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("pRecordList", ctypes.c_void_p),
        ("nMaxRecordNum", ctypes.c_int),
        ("nRetRecordNum", ctypes.c_int),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class NetRecordsetAccessCtlCardRec(ctypes.Structure):
    """Compact access-card event record returned by record search.

    The full Dahua struct contains more fields after nErrorCode. These leading
    fields cover the counter workflow: card number, user id, event time, door,
    reader, success flag, and reject reason.
    """

    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("nRecNo", ctypes.c_int),
        ("szCardNo", ctypes.c_char * NET_MAX_CARDNO_LEN),
        ("szPwd", ctypes.c_char * NET_MAX_CARDPWD_LEN),
        ("stuTime", NetTime),
        ("bStatus", ctypes.c_int),
        ("emMethod", ctypes.c_int),
        ("nDoor", ctypes.c_int),
        ("szUserID", ctypes.c_char * NET_MAX_USERID_LEN),
        ("nReaderID", ctypes.c_int),
        ("szSnapFtpUrl", ctypes.c_char * MAX_PATH),
        ("szReaderID", ctypes.c_char * NET_COMMON_STRING_32),
        ("emCardType", ctypes.c_int),
        ("nErrorCode", ctypes.c_int),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class NetCtrlRecordsetInsertIn(ctypes.Structure):
    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("emType", ctypes.c_int),
        ("pBuf", ctypes.c_void_p),
        ("nBufLen", ctypes.c_int),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class NetCtrlRecordsetInsertOut(ctypes.Structure):
    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("nRecNo", ctypes.c_int),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class NetCtrlRecordsetInsertParam(ctypes.Structure):
    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("stuCtrlRecordSetInfo", NetCtrlRecordsetInsertIn),
        ("stuCtrlRecordSetResult", NetCtrlRecordsetInsertOut),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class FailCode(ctypes.Structure):
    _fields_ = [("nFailCode", ctypes.c_int)]


class AccessFloorInfo(ctypes.Structure):
    _fields_ = [("szFloorNo", ctypes.c_char * MAX_COMMON_STRING_16)]


class RoomInfo(ctypes.Structure):
    _fields_ = [("szRoomNo", ctypes.c_char * MAX_COMMON_STRING_16)]


class FloorNoExInfo(ctypes.Structure):
    _fields_ = [("szFloorNoEx", ctypes.c_char * 4)]


class UserTimeSection(ctypes.Structure):
    _fields_ = [("userTimeSections", ctypes.c_ubyte * 20)]


class NetAccessUserInfo(ctypes.Structure):
    _fields_ = [
        ("szUserID", ctypes.c_char * NET_MAX_USERID_LEN),
        ("szName", ctypes.c_char * MAX_COMMON_STRING_32),
        ("emUserType", ctypes.c_int),
        ("nUserStatus", ctypes.c_int),
        ("nUserTime", ctypes.c_int),
        ("szCitizenIDNo", ctypes.c_char * MAX_COMMON_STRING_32),
        ("szPsw", ctypes.c_char * NET_MAX_CARDPWD_LEN),
        ("nDoorNum", ctypes.c_int),
        ("nDoors", ctypes.c_int * NET_MAX_DOOR_NUM),
        ("nTimeSectionNum", ctypes.c_int),
        ("nTimeSectionNo", ctypes.c_int * NET_MAX_TIMESECTION_NUM),
        ("nSpecialDaysScheduleNum", ctypes.c_int),
        ("nSpecialDaysSchedule", ctypes.c_int * MAX_ACCESSDOOR_NUM),
        ("stuValidBeginTime", NetTime),
        ("stuValidEndTime", NetTime),
        ("bFirstEnter", ctypes.c_int),
        ("nFirstEnterDoorsNum", ctypes.c_int),
        ("nFirstEnterDoors", ctypes.c_int * NET_MAX_DOOR_NUM),
        ("emAuthority", ctypes.c_int),
        ("nRepeatEnterRouteTimeout", ctypes.c_int),
        ("nFloorNum", ctypes.c_int),
        ("szFloorNos", AccessFloorInfo * MAX_ACCESS_FLOOR_NUM),
        ("nRoom", ctypes.c_int),
        ("szRoomNos", RoomInfo * MAX_ROOMNUM_COUNT),
        ("bFloorNoExValid", ctypes.c_int),
        ("nFloorNumEx", ctypes.c_int),
        ("szFloorNoEx", FloorNoExInfo * 256),
        ("szClassInfo", ctypes.c_char * 256),
        ("szStudentNo", ctypes.c_char * 64),
        ("szCitizenAddress", ctypes.c_char * 128),
        ("stuBirthDay", NetTime),
        ("emSex", ctypes.c_int),
        ("szDepartment", ctypes.c_char * 128),
        ("szSiteCode", ctypes.c_char * 32),
        ("szPhoneNumber", ctypes.c_char * 32),
        ("szDefaultFloor", ctypes.c_char * 8),
        ("bFloorNoEx2Valid", ctypes.c_int),
        ("pstuFloorsEx2", ctypes.c_void_p),
        ("bHealthStatus", ctypes.c_int),
        ("nUserTimeSectionsNum", ctypes.c_int),
        ("szUserTimeSections", UserTimeSection * 6),
        ("szEthnicity", ctypes.c_char * 64),
        ("emTypeOfCertificate", ctypes.c_int),
        ("szCountryOrAreaCode", ctypes.c_char * 8),
        ("szCountryOrAreaName", ctypes.c_char * 64),
        ("szCertificateVersionNumber", ctypes.c_char * 64),
        ("szApplicationAgencyCode", ctypes.c_char * 64),
        ("szIssuingAuthority", ctypes.c_char * 64),
        ("szStartTimeOfCertificateValidity", ctypes.c_char * 64),
        ("szEndTimeOfCertificateValidity", ctypes.c_char * 64),
        ("nSignNum", ctypes.c_int),
        ("szActualResidentialAddr", ctypes.c_char * 108),
        ("byReserved", ctypes.c_ubyte * 1732),
    ]


class NetInAccessUserServiceInsert(ctypes.Structure):
    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("nInfoNum", ctypes.c_int),
        ("pUserInfo", ctypes.c_void_p),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class NetOutAccessUserServiceInsert(ctypes.Structure):
    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("nMaxRetNum", ctypes.c_int),
        ("pFailCode", ctypes.c_void_p),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class UserId(ctypes.Structure):
    _fields_ = [("szUserID", ctypes.c_char * NET_MAX_USERID_LEN)]


class NetInAccessUserServiceRemove(ctypes.Structure):
    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("nUserNum", ctypes.c_int),
        ("szUserIDs", UserId * 100),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class NetOutAccessUserServiceRemove(ctypes.Structure):
    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("nMaxRetNum", ctypes.c_int),
        ("pFailCode", ctypes.c_void_p),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class CardNo(ctypes.Structure):
    _fields_ = [("szCardNo", ctypes.c_char * NET_MAX_CARDNO_LEN)]


class NetAccessCardInfo(ctypes.Structure):
    _fields_ = [
        ("szCardNo", ctypes.c_char * NET_MAX_CARDNO_LEN),
        ("szUserID", ctypes.c_char * NET_MAX_USERID_LEN),
        ("emType", ctypes.c_int),
        ("szDynamicCheckCode", ctypes.c_char * MAX_COMMON_STRING_16),
        ("byReserved", ctypes.c_ubyte * 4096),
    ]


class NetInAccessCardServiceInsert(ctypes.Structure):
    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("nInfoNum", ctypes.c_int),
        ("pCardInfo", ctypes.c_void_p),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class NetOutAccessCardServiceInsert(ctypes.Structure):
    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("nMaxRetNum", ctypes.c_int),
        ("pFailCode", ctypes.c_void_p),
        ("byReserved", ctypes.c_ubyte * 4),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class NetInAccessCardServiceRemove(ctypes.Structure):
    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("nCardNum", ctypes.c_int),
        ("szCardNos", CardNo * 100),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class NetOutAccessCardServiceRemove(ctypes.Structure):
    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("nMaxRetNum", ctypes.c_int),
        ("pFailCode", ctypes.c_void_p),
        ("byReserved", ctypes.c_ubyte * 4),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class NetInCardInfoStartFind(ctypes.Structure):
    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("byReserved", ctypes.c_ubyte * 4),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class NetOutCardInfoStartFind(ctypes.Structure):
    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("nTotalCount", ctypes.c_int),
        ("byReserved", ctypes.c_ubyte * 4),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class NetInCardInfoDoFind(ctypes.Structure):
    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("nStartNo", ctypes.c_int),
        ("nCount", ctypes.c_int),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class NetOutCardInfoDoFind(ctypes.Structure):
    _fields_ = [
        ("dwSize", ctypes.c_int),
        ("nRetNum", ctypes.c_int),
        ("pstuCardInfo", ctypes.c_void_p),
        ("nMaxNum", ctypes.c_int),
    ]

    def __init__(self) -> None:
        super().__init__()
        self.dwSize = ctypes.sizeof(self)


class DahuaSdk:
    def __init__(self) -> None:
        sdk_path = next((path for path in SDK_PATHS if os.path.exists(path)), None)
        if sdk_path is None:
            raise RuntimeError("Dahua NetSDK library not found. Install SmartPSS Lite or ConfigTool.")

        self.sdk_path = sdk_path
        self.lib = ctypes.CDLL(sdk_path)
        self._configure()

        if not self.lib.CLIENT_Init(None, 0):
            raise RuntimeError(f"CLIENT_Init failed: {self.last_error()}")

    def _configure(self) -> None:
        self.lib.CLIENT_Init.argtypes = [ctypes.c_void_p, ctypes.c_ulong]
        self.lib.CLIENT_Init.restype = ctypes.c_bool

        self.lib.CLIENT_Cleanup.argtypes = []
        self.lib.CLIENT_Cleanup.restype = None

        self.lib.CLIENT_GetLastError.argtypes = []
        self.lib.CLIENT_GetLastError.restype = ctypes.c_uint

        self.lib.CLIENT_LoginEx2.argtypes = [
            ctypes.c_char_p,
            ctypes.c_ushort,
            ctypes.c_char_p,
            ctypes.c_char_p,
            ctypes.c_int,
            ctypes.c_void_p,
            ctypes.POINTER(NetDeviceInfoEx),
            ctypes.POINTER(ctypes.c_int),
        ]
        self.lib.CLIENT_LoginEx2.restype = ctypes.c_longlong

        self.lib.CLIENT_Logout.argtypes = [ctypes.c_longlong]
        self.lib.CLIENT_Logout.restype = ctypes.c_bool

        self.lib.CLIENT_QueryDeviceTime.argtypes = [
            ctypes.c_longlong,
            ctypes.POINTER(NetTime),
            ctypes.c_int,
        ]
        self.lib.CLIENT_QueryDeviceTime.restype = ctypes.c_bool

        self.lib.CLIENT_SetupDeviceTime.argtypes = [
            ctypes.c_longlong,
            ctypes.POINTER(NetTime),
        ]
        self.lib.CLIENT_SetupDeviceTime.restype = ctypes.c_bool

        self.lib.CLIENT_ControlDevice.argtypes = [
            ctypes.c_longlong,
            ctypes.c_int,
            ctypes.c_void_p,
            ctypes.c_int,
        ]
        self.lib.CLIENT_ControlDevice.restype = ctypes.c_bool

        self.has_access_user_service = hasattr(self.lib, "CLIENT_OperateAccessUserService")
        if self.has_access_user_service:
            self.lib.CLIENT_OperateAccessUserService.argtypes = [
                ctypes.c_longlong,
                ctypes.c_int,
                ctypes.c_void_p,
                ctypes.c_void_p,
                ctypes.c_int,
            ]
            self.lib.CLIENT_OperateAccessUserService.restype = ctypes.c_bool

        self.has_access_card_service = hasattr(self.lib, "CLIENT_OperateAccessCardService")
        if self.has_access_card_service:
            self.lib.CLIENT_OperateAccessCardService.argtypes = [
                ctypes.c_longlong,
                ctypes.c_int,
                ctypes.c_void_p,
                ctypes.c_void_p,
                ctypes.c_int,
            ]
            self.lib.CLIENT_OperateAccessCardService.restype = ctypes.c_bool

        self.has_record_search = all(
            hasattr(self.lib, name)
            for name in ("CLIENT_FindRecord", "CLIENT_FindNextRecord", "CLIENT_FindRecordClose")
        )
        if self.has_record_search:
            self.lib.CLIENT_FindRecord.argtypes = [
                ctypes.c_longlong,
                ctypes.POINTER(NetInFindRecordParam),
                ctypes.POINTER(NetOutFindRecordParam),
                ctypes.c_int,
            ]
            self.lib.CLIENT_FindRecord.restype = ctypes.c_bool

            self.lib.CLIENT_FindNextRecord.argtypes = [
                ctypes.POINTER(NetInFindNextRecordParam),
                ctypes.POINTER(NetOutFindNextRecordParam),
                ctypes.c_int,
            ]
            self.lib.CLIENT_FindNextRecord.restype = ctypes.c_bool

            self.lib.CLIENT_FindRecordClose.argtypes = [ctypes.c_longlong]
            self.lib.CLIENT_FindRecordClose.restype = ctypes.c_bool

        self.has_card_info_search = all(
            hasattr(self.lib, name)
            for name in ("CLIENT_StartFindCardInfo", "CLIENT_DoFindCardInfo", "CLIENT_StopFindCardInfo")
        )
        if self.has_card_info_search:
            self.lib.CLIENT_StartFindCardInfo.argtypes = [
                ctypes.c_longlong,
                ctypes.POINTER(NetInCardInfoStartFind),
                ctypes.POINTER(NetOutCardInfoStartFind),
                ctypes.c_int,
            ]
            self.lib.CLIENT_StartFindCardInfo.restype = ctypes.c_longlong

            self.lib.CLIENT_DoFindCardInfo.argtypes = [
                ctypes.c_longlong,
                ctypes.POINTER(NetInCardInfoDoFind),
                ctypes.POINTER(NetOutCardInfoDoFind),
                ctypes.c_int,
            ]
            self.lib.CLIENT_DoFindCardInfo.restype = ctypes.c_bool

            self.lib.CLIENT_StopFindCardInfo.argtypes = [ctypes.c_longlong]
            self.lib.CLIENT_StopFindCardInfo.restype = ctypes.c_bool

    def last_error(self) -> int:
        return int(self.lib.CLIENT_GetLastError())

    def login(self, door: dict[str, Any]) -> int:
        host = str(door.get("host") or "").encode()
        username = str(door.get("username") or "").encode()
        password = str(door.get("password") or "").encode()
        port = int(door.get("port") or 37777)
        device_info = NetDeviceInfoEx()
        error = ctypes.c_int(0)

        handle = self.lib.CLIENT_LoginEx2(
            host,
            port,
            username,
            password,
            0,
            None,
            ctypes.byref(device_info),
            ctypes.byref(error),
        )

        if not handle:
            raise RuntimeError(f"Login failed. sdk_error={self.last_error()} login_error={error.value}")

        return int(handle)

    def logout(self, handle: int) -> None:
        self.lib.CLIENT_Logout(ctypes.c_longlong(handle))

    def query_time(self, door: dict[str, Any]) -> dict[str, Any]:
        handle = self.login(door)
        try:
            device_time = NetTime()
            if not self.lib.CLIENT_QueryDeviceTime(ctypes.c_longlong(handle), ctypes.byref(device_time), 5000):
                raise RuntimeError(f"Query time failed. sdk_error={self.last_error()}")

            return {
                "device_time": self._format_time(device_time),
                "sdk_path": self.sdk_path,
            }
        finally:
            self.logout(handle)

    def sync_time(self, door: dict[str, Any], options: dict[str, Any]) -> dict[str, Any]:
        requested_time = str(options.get("device_time") or "")
        parsed_time = datetime.strptime(requested_time, "%Y-%m-%d %H:%M:%S") if requested_time else datetime.now()
        sdk_time = NetTime(
            parsed_time.year,
            parsed_time.month,
            parsed_time.day,
            parsed_time.hour,
            parsed_time.minute,
            parsed_time.second,
        )

        handle = self.login(door)
        try:
            if not self.lib.CLIENT_SetupDeviceTime(ctypes.c_longlong(handle), ctypes.byref(sdk_time)):
                raise RuntimeError(f"Set time failed. sdk_error={self.last_error()}")

            return {
                "device_time": self._format_time(sdk_time),
                "timezone": options.get("timezone"),
                "sdk_path": self.sdk_path,
            }
        finally:
            self.logout(handle)

    def sync_card(self, payload: dict[str, Any]) -> dict[str, Any]:
        action = str(payload.get("action") or "").lower()
        door = payload.get("door") or {}
        user = payload.get("user") or {}
        card = payload.get("card") or {}
        validity = payload.get("validity") or {}

        card_number = self._card_number(card)
        input_card_number = self._clean_text(card.get("number"), NET_MAX_CARDNO_LEN - 1)

        if not action:
            raise ValueError("Sync action is required.")

        handle = self.login(door)
        try:
            if self._is_disable_action(action):
                remove_result = self._remove_card(handle, card_number)
                cleanup_results = []
                if input_card_number and input_card_number != card_number:
                    cleanup_results.append(self._try_remove_card(handle, input_card_number))

                return {
                    "action": action,
                    "operation": "remove-card",
                    "card_number": card_number,
                    "input_card_number": input_card_number,
                    "user_id": self._clean_text(user.get("id") or user.get("member_no") or user.get("member_id"), NET_MAX_USERID_LEN - 1),
                    **remove_result,
                    "cleanup": cleanup_results,
                    "sdk_path": self.sdk_path,
                }

            if not self._is_enable_action(action):
                raise ValueError(f"Unsupported sync action: {action}")

            user_id = self._device_user_id(user)
            user_name = self._clean_text(user.get("name") or user.get("member_no") or user_id, 64)
            user_result = self._upsert_user_service(handle, user_id, user_name, validity)
            card_result = self._upsert_card_service(handle, card_number, user_id)

            if user_result.get("ok") and card_result.get("ok"):
                return {
                    "action": action,
                    "operation": "upsert-user-card",
                    "card_number": card_number,
                    "input_card_number": input_card_number,
                    "user_id": user_id,
                    "user_service": user_result,
                    "card_service": card_result,
                    "sdk_path": self.sdk_path,
                }

            record_result = self._insert_access_card(handle, card_number, user_id, user_name, validity)
            return {
                "action": action,
                "operation": "insert-access-card-recordset",
                "card_number": card_number,
                "input_card_number": input_card_number,
                "user_id": user_id,
                "user_service": user_result,
                "card_service": card_result,
                **record_result,
                "sdk_path": self.sdk_path,
            }
        finally:
            self.logout(handle)

    def access_history(self, payload: dict[str, Any]) -> dict[str, Any]:
        if not self.has_record_search:
            raise RuntimeError("CLIENT_FindRecord is not available in this SDK.")

        door = payload.get("door") or {}
        filters = payload.get("filters") or {}
        limit = max(1, min(int(filters.get("limit") or 50), 200))
        batch_size = max(1, min(limit, 50))
        now = datetime.now()
        start_time = self._parse_date(filters.get("date_from"), datetime(now.year, now.month, now.day, 0, 0, 0))
        end_time = self._parse_date(filters.get("date_to"), datetime(now.year, now.month, now.day, 23, 59, 59), end_of_day=True)
        card_number_filter = self._clean_text(filters.get("card_number"), NET_MAX_CARDNO_LEN - 1)
        card_number_filter = "".join(ch for ch in card_number_filter if ch.isalnum()).upper()

        condition = FindRecordAccessCtlCardRecConditionEx()
        condition.bTimeEnable = 1
        condition.stStartTime = self._net_time(start_time)
        condition.stEndTime = self._net_time(end_time)
        condition.nOrderNum = 1
        condition.stuOrders[0].emField = EM_RECORD_ACCESSCTLCARDREC_ORDER_FIELD_CREATETIME
        condition.stuOrders[0].emOrderType = EM_RECORD_ORDER_TYPE_DESCENT
        input_param = NetInFindRecordParam()
        output_param = NetOutFindRecordParam()
        input_param.emType = NET_RECORD_ACCESSCTLCARDREC_EX
        input_param.pQueryCondition = ctypes.cast(ctypes.byref(condition), ctypes.c_void_p)

        handle = self.login(door)
        find_handle = 0
        try:
            ok = self.lib.CLIENT_FindRecord(
                ctypes.c_longlong(handle),
                ctypes.byref(input_param),
                ctypes.byref(output_param),
                8000,
            )
            if not ok or not output_param.lFindeHandle:
                raise RuntimeError(f"Find access history failed. sdk_error={self.last_error()}")

            find_handle = int(output_param.lFindeHandle)
            records: list[dict[str, Any]] = []

            while len(records) < limit:
                remaining = limit - len(records)
                requested = min(batch_size, remaining)
                record_array = (NetRecordsetAccessCtlCardRec * requested)()
                for record in record_array:
                    record.dwSize = ctypes.sizeof(NetRecordsetAccessCtlCardRec)

                next_input = NetInFindNextRecordParam()
                next_output = NetOutFindNextRecordParam()
                next_input.lFindeHandle = find_handle
                next_input.nFileCount = requested
                next_output.pRecordList = ctypes.cast(record_array, ctypes.c_void_p)
                next_output.nMaxRecordNum = requested

                ok = self.lib.CLIENT_FindNextRecord(
                    ctypes.byref(next_input),
                    ctypes.byref(next_output),
                    8000,
                )
                if not ok:
                    raise RuntimeError(f"Find next access history failed. sdk_error={self.last_error()}")
                if next_output.nRetRecordNum <= 0:
                    break

                for index in range(min(next_output.nRetRecordNum, requested)):
                    event = self._serialize_access_record(record_array[index], door)
                    if card_number_filter and not self._matches_card_filter(event, card_number_filter):
                        continue

                    records.append(event)

            return {
                "records": records,
                "count": len(records),
                "filters": {
                    "card_number": card_number_filter,
                    "date_from": start_time.strftime("%Y-%m-%d %H:%M:%S"),
                    "date_to": end_time.strftime("%Y-%m-%d %H:%M:%S"),
                    "limit": limit,
                },
                "sdk_path": self.sdk_path,
            }
        finally:
            if find_handle:
                self.lib.CLIENT_FindRecordClose(ctypes.c_longlong(find_handle))
            self.logout(handle)

    def card_list(self, payload: dict[str, Any]) -> dict[str, Any]:
        door = payload.get("door") or {}
        options = payload.get("options") or {}
        limit = max(1, min(int(options.get("limit") or 500), 5000))
        batch_size = max(1, min(limit, 100))

        if not self.has_card_info_search:
            return self._card_list_via_record_search(door, limit, "card-info search unavailable")

        handle = self.login(door)
        find_handle = None
        try:
            start_input = NetInCardInfoStartFind()
            start_output = NetOutCardInfoStartFind()
            find_handle = self.lib.CLIENT_StartFindCardInfo(
                ctypes.c_longlong(handle),
                ctypes.byref(start_input),
                ctypes.byref(start_output),
                8000,
            )
            if not find_handle:
                raise RuntimeError(f"Start card list search failed. sdk_error={self.last_error()}")

            cards: list[dict[str, Any]] = []
            total_count = int(start_output.nTotalCount)
            cursor = 0

            while len(cards) < limit and (total_count <= 0 or cursor < total_count):
                remaining = limit - len(cards)
                requested = min(batch_size, remaining)
                card_array = (NetAccessCardInfo * requested)()

                find_input = NetInCardInfoDoFind()
                find_output = NetOutCardInfoDoFind()
                find_input.nStartNo = cursor
                find_input.nCount = requested
                find_output.nMaxNum = requested
                find_output.pstuCardInfo = ctypes.cast(card_array, ctypes.c_void_p)

                ok = self.lib.CLIENT_DoFindCardInfo(
                    ctypes.c_longlong(find_handle),
                    ctypes.byref(find_input),
                    ctypes.byref(find_output),
                    8000,
                )
                if not ok:
                    sdk_error = self.last_error()
                    if find_handle:
                        self.lib.CLIENT_StopFindCardInfo(ctypes.c_longlong(find_handle))
                        find_handle = None

                    self.logout(handle)
                    handle = 0

                    return self._card_list_via_record_search(
                        door,
                        limit,
                        f"card-info batch failed. sdk_error={sdk_error}",
                    )
                if find_output.nRetNum <= 0:
                    break

                for index in range(min(find_output.nRetNum, requested)):
                    cards.append(self._serialize_card_info(card_array[index], door))

                cursor += int(find_output.nRetNum)

            return {
                "cards": cards,
                "count": len(cards),
                "total_count": total_count,
                "limit": limit,
                "sdk_path": self.sdk_path,
            }
        finally:
            if find_handle:
                self.lib.CLIENT_StopFindCardInfo(ctypes.c_longlong(find_handle))
            if handle:
                self.logout(handle)

    def _card_list_via_record_search(self, door: dict[str, Any], limit: int, fallback_reason: str) -> dict[str, Any]:
        if not self.has_record_search:
            raise RuntimeError(f"Card list fallback failed. {fallback_reason}; CLIENT_FindRecord is not available in this SDK.")

        batch_size = max(1, min(limit, 50))
        input_param = NetInFindRecordParam()
        output_param = NetOutFindRecordParam()
        input_param.emType = NET_RECORD_ACCESSCTLCARD
        input_param.pQueryCondition = None

        handle = self.login(door)
        find_handle = 0
        try:
            ok = self.lib.CLIENT_FindRecord(
                ctypes.c_longlong(handle),
                ctypes.byref(input_param),
                ctypes.byref(output_param),
                8000,
            )
            if not ok or not output_param.lFindeHandle:
                raise RuntimeError(f"Find card record list failed. sdk_error={self.last_error()}; fallback_reason={fallback_reason}")

            find_handle = int(output_param.lFindeHandle)
            cards: list[dict[str, Any]] = []

            while len(cards) < limit:
                remaining = limit - len(cards)
                requested = min(batch_size, remaining)
                record_array = (NetRecordsetAccessCtlCard * requested)()
                for record in record_array:
                    record.dwSize = ctypes.sizeof(NetRecordsetAccessCtlCard)

                next_input = NetInFindNextRecordParam()
                next_output = NetOutFindNextRecordParam()
                next_input.lFindeHandle = find_handle
                next_input.nFileCount = requested
                next_output.pRecordList = ctypes.cast(record_array, ctypes.c_void_p)
                next_output.nMaxRecordNum = requested

                ok = self.lib.CLIENT_FindNextRecord(
                    ctypes.byref(next_input),
                    ctypes.byref(next_output),
                    8000,
                )
                if not ok:
                    raise RuntimeError(f"Find next card record list failed. sdk_error={self.last_error()}; fallback_reason={fallback_reason}")
                if next_output.nRetRecordNum <= 0:
                    break

                for index in range(min(next_output.nRetRecordNum, requested)):
                    cards.append(self._serialize_card_record(record_array[index], door))

            return {
                "cards": cards,
                "count": len(cards),
                "total_count": len(cards),
                "limit": limit,
                "source": "record-search",
                "fallback_reason": fallback_reason,
                "sdk_path": self.sdk_path,
            }
        finally:
            if find_handle:
                self.lib.CLIENT_FindRecordClose(ctypes.c_longlong(find_handle))
            self.logout(handle)

    def _insert_access_card(
        self,
        handle: int,
        card_number: str,
        user_id: str,
        user_name: str,
        validity: dict[str, Any],
    ) -> dict[str, Any]:
        record = NetRecordsetAccessCtlCard()
        now = datetime.now()
        start_time = self._parse_date(validity.get("start_date"), datetime(now.year, now.month, now.day, 0, 0, 0))
        end_time = self._parse_date(validity.get("end_date"), datetime(now.year, now.month, now.day, 23, 59, 59), end_of_day=True)

        self._set_c_string(record, "szCardNo", card_number)
        self._set_c_string(record, "szUserID", user_id)
        record.stuCreateTime = self._net_time(now)
        record.emStatus = NET_ACCESSCTLCARD_STATE_NORMAL
        record.emType = NET_ACCESSCTLCARD_TYPE_GENERAL
        record.nDoorNum = 1
        record.sznDoors[0] = self._door_index()
        record.nTimeSectionNum = 1
        record.sznTimeSectionNo[0] = self._time_section_index()
        record.nUserTime = 0
        record.stuValidStartTime = self._net_time(start_time)
        record.stuValidEndTime = self._net_time(end_time)
        record.bIsValid = 1

        insert_param = NetCtrlRecordsetInsertParam()
        insert_param.stuCtrlRecordSetInfo.emType = NET_RECORD_ACCESSCTLCARD
        insert_param.stuCtrlRecordSetInfo.pBuf = ctypes.cast(ctypes.byref(record), ctypes.c_void_p)
        insert_param.stuCtrlRecordSetInfo.nBufLen = ctypes.sizeof(record)

        ok = self.lib.CLIENT_ControlDevice(
            ctypes.c_longlong(handle),
            CTRLTYPE_CTRL_RECORDSET_INSERT,
            ctypes.byref(insert_param),
            8000,
        )

        if ok:
            return {
                "record_no": int(insert_param.stuCtrlRecordSetResult.nRecNo),
                "valid_from": self._format_time(record.stuValidStartTime),
                "valid_to": self._format_time(record.stuValidEndTime),
                "door_index": int(record.sznDoors[0]),
                "time_section_index": int(record.sznTimeSectionNo[0]),
            }

        sdk_error = self.last_error()
        fallback = self._upsert_card_service(handle, card_number, user_id)
        if fallback.get("ok"):
            return {
                "record_no": None,
                "valid_from": self._format_time(record.stuValidStartTime),
                "valid_to": self._format_time(record.stuValidEndTime),
                "door_index": int(record.sznDoors[0]),
                "time_section_index": int(record.sznTimeSectionNo[0]),
                "fallback": fallback,
                "warning": "Record-set insert failed, but card-service insert/update succeeded. Device may require validity setup from its local access schedule.",
                "recordset_sdk_error": sdk_error,
            }

        raise RuntimeError("Access card sync failed. "+json.dumps({
            "recordset_sdk_error": sdk_error,
            "card_service": fallback,
        }))

    def _upsert_user_service(self, handle: int, user_id: str, user_name: str, validity: dict[str, Any]) -> dict[str, Any]:
        if not self.has_access_user_service:
            return {"ok": False, "message": "CLIENT_OperateAccessUserService is not available in this SDK."}

        now = datetime.now()
        start_time = self._parse_date(validity.get("start_date"), datetime(now.year, now.month, now.day, 0, 0, 0))
        end_time = self._parse_date(validity.get("end_date"), datetime(now.year, now.month, now.day, 23, 59, 59), end_of_day=True)

        user_info = NetAccessUserInfo()
        self._set_c_string(user_info, "szUserID", user_id)
        self._set_c_string(user_info, "szName", user_name)
        user_info.emUserType = 0
        user_info.nUserStatus = 0
        user_info.nDoorNum = 1
        user_info.nDoors[0] = self._door_index()
        user_info.nTimeSectionNum = 1
        user_info.nTimeSectionNo[0] = self._time_section_index()
        user_info.stuValidBeginTime = self._net_time(start_time)
        user_info.stuValidEndTime = self._net_time(end_time)
        user_info.emAuthority = 0

        input_param = NetInAccessUserServiceInsert()
        output_param = NetOutAccessUserServiceInsert()
        fail_code = FailCode()
        input_param.nInfoNum = 1
        input_param.pUserInfo = ctypes.cast(ctypes.byref(user_info), ctypes.c_void_p)
        output_param.nMaxRetNum = 1
        output_param.pFailCode = ctypes.cast(ctypes.byref(fail_code), ctypes.c_void_p)

        ok = self.lib.CLIENT_OperateAccessUserService(
            ctypes.c_longlong(handle),
            NET_EM_ACCESS_CTL_USER_SERVICE_INSERT,
            ctypes.byref(input_param),
            ctypes.byref(output_param),
            8000,
        )

        return {
            "ok": bool(ok),
            "operation": "insert-or-update",
            "sdk_error": None if ok else self.last_error(),
            "fail_code": int(fail_code.nFailCode),
            "valid_from": start_time.strftime("%Y-%m-%d %H:%M:%S"),
            "valid_to": end_time.strftime("%Y-%m-%d %H:%M:%S"),
            "door_index": self._door_index(),
            "time_section_index": self._time_section_index(),
            "struct_size": ctypes.sizeof(user_info),
        }

    def _upsert_card_service(self, handle: int, card_number: str, user_id: str) -> dict[str, Any]:
        if not self.has_access_card_service:
            return {"ok": False, "message": "CLIENT_OperateAccessCardService is not available in this SDK."}

        insert_result = self._card_service_insert_or_update(
            handle,
            NET_EM_ACCESS_CTL_CARD_SERVICE_INSERT,
            card_number,
            user_id,
        )

        if insert_result.get("ok"):
            return insert_result

        update_result = self._card_service_insert_or_update(
            handle,
            NET_EM_ACCESS_CTL_CARD_SERVICE_UPDATE,
            card_number,
            user_id,
        )
        if update_result.get("ok"):
            update_result["previous_insert_error"] = insert_result
            return update_result

        return {
            "ok": False,
            "message": "card-service insert and update failed",
            "insert": insert_result,
            "update": update_result,
        }

    def _card_service_insert_or_update(self, handle: int, operation: int, card_number: str, user_id: str) -> dict[str, Any]:
        card_info = NetAccessCardInfo()
        self._set_c_string(card_info, "szCardNo", card_number)
        self._set_c_string(card_info, "szUserID", user_id)
        card_info.emType = NET_ACCESSCTLCARD_TYPE_GENERAL

        input_param = NetInAccessCardServiceInsert()
        output_param = NetOutAccessCardServiceInsert()
        fail_code = FailCode()
        input_param.nInfoNum = 1
        input_param.pCardInfo = ctypes.cast(ctypes.byref(card_info), ctypes.c_void_p)
        output_param.nMaxRetNum = 1
        output_param.pFailCode = ctypes.cast(ctypes.byref(fail_code), ctypes.c_void_p)

        ok = self.lib.CLIENT_OperateAccessCardService(
            ctypes.c_longlong(handle),
            operation,
            ctypes.byref(input_param),
            ctypes.byref(output_param),
            8000,
        )

        return {
            "ok": bool(ok),
            "operation": "insert" if operation == NET_EM_ACCESS_CTL_CARD_SERVICE_INSERT else "update",
            "sdk_error": None if ok else self.last_error(),
            "fail_code": int(fail_code.nFailCode),
        }

    def _remove_card(self, handle: int, card_number: str) -> dict[str, Any]:
        if not self.has_access_card_service:
            raise RuntimeError("CLIENT_OperateAccessCardService is not available in this SDK.")

        input_param = NetInAccessCardServiceRemove()
        output_param = NetOutAccessCardServiceRemove()
        fail_code = FailCode()
        input_param.nCardNum = 1
        self._set_c_string(input_param.szCardNos[0], "szCardNo", card_number)
        output_param.nMaxRetNum = 1
        output_param.pFailCode = ctypes.cast(ctypes.byref(fail_code), ctypes.c_void_p)

        ok = self.lib.CLIENT_OperateAccessCardService(
            ctypes.c_longlong(handle),
            NET_EM_ACCESS_CTL_CARD_SERVICE_REMOVE,
            ctypes.byref(input_param),
            ctypes.byref(output_param),
            8000,
        )

        if not ok:
            raise RuntimeError(f"Remove card failed. sdk_error={self.last_error()} fail_code={fail_code.nFailCode}")

        return {"fail_code": int(fail_code.nFailCode)}

    def _try_remove_card(self, handle: int, card_number: str) -> dict[str, Any]:
        try:
            return {
                "card_number": card_number,
                "status": "success",
                **self._remove_card(handle, card_number),
            }
        except Exception as exc:
            return {
                "card_number": card_number,
                "status": "skipped",
                "message": str(exc),
            }

    def _serialize_access_record(self, record: NetRecordsetAccessCtlCardRec, door: dict[str, Any]) -> dict[str, Any]:
        error_code = int(record.nErrorCode)
        card_number = self._c_string(record.szCardNo)

        return {
            "record_no": int(record.nRecNo),
            "door_name": str(door.get("name") or ""),
            "door_index": int(record.nDoor),
            "reader_id": self._c_string(record.szReaderID) or str(int(record.nReaderID)),
            "time": self._format_time(record.stuTime),
            "card_number": card_number,
            "card_number_decimal": self._hex_card_to_decimal(card_number),
            "user_id": self._c_string(record.szUserID),
            "success": bool(record.bStatus),
            "method": int(record.emMethod),
            "method_label": self._access_method_label(int(record.emMethod)),
            "card_type": int(record.emCardType),
            "error_code": error_code,
            "error_label": self._access_error_label(error_code),
        }

    def _serialize_card_record(self, record: NetRecordsetAccessCtlCard, door: dict[str, Any]) -> dict[str, Any]:
        card_number = self._c_string(record.szCardNo)

        return {
            "record_no": int(record.nRecNo),
            "door_name": str(door.get("name") or ""),
            "card_number": card_number,
            "card_number_decimal": self._hex_card_to_decimal(card_number),
            "user_id": self._c_string(record.szUserID),
            "status": int(record.emStatus),
            "status_label": self._card_status_label(int(record.emStatus)),
            "card_type": int(record.emType),
            "door_indexes": [int(record.sznDoors[index]) for index in range(max(0, min(int(record.nDoorNum), NET_MAX_DOOR_NUM)))],
            "time_section_indexes": [
                int(record.sznTimeSectionNo[index])
                for index in range(max(0, min(int(record.nTimeSectionNum), NET_MAX_TIMESECTION_NUM)))
            ],
            "valid_from": self._format_time(record.stuValidStartTime),
            "valid_to": self._format_time(record.stuValidEndTime),
            "is_valid": bool(record.bIsValid),
        }

    def _serialize_card_info(self, record: NetAccessCardInfo, door: dict[str, Any]) -> dict[str, Any]:
        card_number = self._c_string(record.szCardNo)

        return {
            "door_name": str(door.get("name") or ""),
            "card_number": card_number,
            "card_number_decimal": self._hex_card_to_decimal(card_number),
            "user_id": self._c_string(record.szUserID),
            "card_type": int(record.emType),
            "is_valid": True,
        }

    @staticmethod
    def _format_time(device_time: NetTime) -> str:
        return (
            f"{device_time.dwYear:04d}-{device_time.dwMonth:02d}-{device_time.dwDay:02d} "
            f"{device_time.dwHour:02d}:{device_time.dwMinute:02d}:{device_time.dwSecond:02d}"
        )

    @staticmethod
    def _net_time(value: datetime) -> NetTime:
        return NetTime(value.year, value.month, value.day, value.hour, value.minute, value.second)

    @staticmethod
    def _set_c_string(target: Any, field_name: str, value: str) -> None:
        field_len = next(field_type._length_ for name, field_type in target._fields_ if name == field_name)
        encoded = value.encode("utf-8")[: max(0, field_len - 1)]
        setattr(target, field_name, encoded)

    @staticmethod
    def _c_string(value: Any) -> str:
        try:
            raw = bytes(value)
        except TypeError:
            raw = str(value or "").encode()

        return raw.split(b"\x00", 1)[0].decode("utf-8", errors="ignore").strip()

    @staticmethod
    def _clean_text(value: Any, max_len: int) -> str:
        text = str(value or "").strip()
        return text[:max_len]

    @staticmethod
    def _access_method_label(method: int) -> str:
        return {
            0: "Unknown",
            1: "Password",
            2: "Card",
            3: "Card, then password",
            4: "Password, then card",
            5: "Remote open",
            6: "Exit button",
            7: "Fingerprint",
            15: "QR code",
            16: "Face",
        }.get(method, f"Method {method}")

    @staticmethod
    def _hex_card_to_decimal(card_number: str) -> str:
        normalized = card_number.strip().upper()

        if not normalized or not all(ch in "0123456789ABCDEF" for ch in normalized):
            return ""

        if not any(ch in "ABCDEF" for ch in normalized):
            return ""

        return str(int(normalized, 16)).zfill(10)

    @staticmethod
    def _matches_card_filter(event: dict[str, Any], card_number_filter: str) -> bool:
        normalized_filter = card_number_filter.lstrip("0") or "0"
        candidates = [
            str(event.get("card_number") or "").upper(),
            str(event.get("card_number_decimal") or "").upper(),
        ]

        return any(
            candidate == card_number_filter
            or (candidate.lstrip("0") or "0") == normalized_filter
            for candidate in candidates
        )

    @staticmethod
    def _access_error_label(error_code: int) -> str:
        return {
            0x00: "No error",
            0x10: "Unauthorized card",
            0x11: "Lost or cancelled card",
            0x12: "No door permission",
            0x13: "Open mode error",
            0x14: "Card validity expired or not active yet",
            0x15: "Anti-passback restriction",
            0x20: "Time schedule restriction",
            0x30: "First-card required",
            0x40: "Interlock door restriction",
            0x41: "Multi-card verification required",
            0x42: "Card has no valid user",
        }.get(error_code, f"Dahua error {error_code}")

    @staticmethod
    def _card_status_label(status: int) -> str:
        return {
            NET_ACCESSCTLCARD_STATE_NORMAL: "Normal",
            NET_ACCESSCTLCARD_STATE_LOGOFF: "Logoff",
        }.get(status, f"Status {status}")

    def _card_number(self, card: dict[str, Any]) -> str:
        raw_number = self._clean_text(card.get("number"), NET_MAX_CARDNO_LEN - 1)
        card_format = str(card.get("format") or "decimal").lower()

        if card_format == "hex":
            card_number = "".join(ch for ch in raw_number.upper() if ch in "0123456789ABCDEF")
        else:
            digits = "".join(ch for ch in raw_number if ch.isdigit())
            card_number = f"{int(digits):08X}" if digits else ""

        if not card_number:
            raise ValueError("Card number is required.")

        return card_number[: NET_MAX_CARDNO_LEN - 1]

    def _device_user_id(self, user: dict[str, Any]) -> str:
        raw_user_id = self._clean_text(
            user.get("id") or user.get("member_no") or user.get("member_id"),
            NET_MAX_USERID_LEN - 1,
        )
        user_id = "".join(ch for ch in raw_user_id if ch.isalnum())

        if not user_id:
            raise ValueError("Device user id is required.")

        return user_id[: NET_MAX_USERID_LEN - 1]

    @staticmethod
    def _parse_date(value: Any, fallback: datetime, end_of_day: bool = False) -> datetime:
        text = str(value or "").strip()
        if not text:
            return fallback

        for fmt in ("%Y-%m-%d", "%Y-%m-%d %H:%M:%S", "%d/%m/%Y"):
            try:
                parsed = datetime.strptime(text, fmt)
                if "%H" not in fmt:
                    return parsed.replace(hour=23, minute=59, second=59) if end_of_day else parsed
                return parsed
            except ValueError:
                continue

        return fallback

    @staticmethod
    def _door_index() -> int:
        return max(0, min(int(os.environ.get("DAHUA_ACCESS_DOOR_INDEX", "0")), NET_MAX_DOOR_NUM - 1))

    @staticmethod
    def _time_section_index() -> int:
        return max(0, min(int(os.environ.get("DAHUA_ACCESS_TIME_SECTION_INDEX", "0")), NET_MAX_TIMESECTION_NUM - 1))

    @staticmethod
    def _is_enable_action(action: str) -> bool:
        return action in {
            "add_card",
            "update_card",
            "enable_card",
            "enable_access",
            "activate_access",
            "assign_card",
            "sync_card",
            "upsert_card",
            "full_sync",
        }

    @staticmethod
    def _is_disable_action(action: str) -> bool:
        return action in {
            "delete_card",
            "disable_card",
            "disable_access",
            "suspend_access",
            "revoke_access",
            "remove_card",
        }


SDK = DahuaSdk()


class BridgeHandler(BaseHTTPRequestHandler):
    server_version = "MACS-DahuaSDKBridge/0.1"

    def do_GET(self) -> None:
        if self.path == "/health":
            self._json(200, {"ok": True, "sdk_path": SDK.sdk_path})
            return

        self._json(404, {"ok": False, "message": "Not found"})

    def do_POST(self) -> None:
        if self.path not in {"/dahua/door-command", "/dahua/sync-card", "/dahua/access-history", "/dahua/card-list"}:
            self._json(404, {"ok": False, "message": "Not found"})
            return

        try:
            length = int(self.headers.get("Content-Length", "0"))
            payload = json.loads(self.rfile.read(length).decode() or "{}")

            if self.path == "/dahua/access-history":
                result = SDK.access_history(payload)
                self._json(200, {"ok": True, **result})
                return

            if self.path == "/dahua/card-list":
                result = SDK.card_list(payload)
                self._json(200, {"ok": True, **result})
                return

            if self.path == "/dahua/sync-card":
                result = SDK.sync_card(payload)
                self._json(200, {"ok": True, **result})
                return

            command = str(payload.get("command") or "").lower()
            door = payload.get("door") or {}
            options = payload.get("options") or {}

            if command == "status":
                result = SDK.query_time(door)
            elif command == "sync-time":
                result = SDK.sync_time(door, options)
            elif command in {"lock", "unlock"}:
                self._json(501, {
                    "ok": False,
                    "message": f"{command} is not enabled in this bridge yet. Use hardware relay/card validation for lock testing.",
                })
                return
            else:
                self._json(400, {"ok": False, "message": f"Unsupported command: {command}"})
                return

            self._json(200, {"ok": True, "command": command, **result})
        except Exception as exc:
            self._json(500, {"ok": False, "message": str(exc)})

    def log_message(self, format: str, *args: Any) -> None:
        print(f"[dahua-bridge] {self.address_string()} {format % args}")

    def _json(self, status: int, body: dict[str, Any]) -> None:
        encoded = json.dumps(body).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(encoded)))
        self.end_headers()
        self.wfile.write(encoded)


if __name__ == "__main__":
    host = os.environ.get("DAHUA_BRIDGE_HOST", "127.0.0.1")
    port = int(os.environ.get("DAHUA_BRIDGE_PORT", "8787"))
    print(f"MACS Dahua SDK bridge listening on http://{host}:{port}")
    print(f"Using SDK: {SDK.sdk_path}")
    ThreadingHTTPServer((host, port), BridgeHandler).serve_forever()
