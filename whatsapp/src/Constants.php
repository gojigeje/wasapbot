<?php

class Constants
{
    /**
     * Constant declarations.
     */
    const CONNECTED_STATUS = 'connected';                                                    // Describes the connection status with the WhatsApp server.
    const DISCONNECTED_STATUS = 'disconnected';                                              // Describes the connection status with the WhatsApp server.
    const MEDIA_FOLDER = 'media';                                                            // The relative folder to store received media files
    const PICTURES_FOLDER = 'pictures';                                                      // The relative folder to store picture files
    const DATA_FOLDER = 'wadata';                                                            // The relative folder to store cache files.
    const PORT = 443;                                                                        // The port of the WhatsApp server.
    const TIMEOUT_SEC = 2;                                                                   // The timeout for the connection with the WhatsApp servers.
    const TIMEOUT_USEC = 0;
    const WHATSAPP_CHECK_HOST = 'v.whatsapp.net/v2/exist';                                   // The check credentials host.
    const WHATSAPP_GROUP_SERVER = 'g.us';                                                    // The Group server hostname
    const WHATSAPP_REGISTER_HOST = 'v.whatsapp.net/v2/register';                             // The register code host.
    const WHATSAPP_REQUEST_HOST = 'v.whatsapp.net/v2/code';                                  // The request code host.
    const WHATSAPP_SERVER = 's.whatsapp.net';                                                // The hostname used to login/send messages.
    const DEVICE = 'armani';                                                                 // The device name.
    const WHATSAPP_VER = '2.12.391';                                                         // The WhatsApp version.
    const OS_VERSION = '4.3';
    const MANUFACTURER = 'Xiaomi';
    const BUILD_VERSION = 'JLS36C';
    const PLATFORM = 'Android';                                                              // The device name.
    const WHATSAPP_USER_AGENT = 'WhatsApp/2.12.391 Android/4.3 Device/Xiaomi-HM_1SW';        // User agent used in request/registration code.
    const WHATSAPP_VER_CHECKER = 'https://coderus.openrepos.net/whitesoft/whatsapp_scratch'; // Check WhatsApp version
}
