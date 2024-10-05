import { useState } from 'react';

import getSetting from '../hooks/wooCommerceOptions';

const debug = false;
const Logger = async (function_name, message, isJson = false) => {

    if (!debug) {
        const logging = await getSetting('bjorntech_wcar_logging');

        if (!logging === 'yes') return;

        const nonce = await getSetting('bjorntech_wcar_nonce');

        fetch('/wp-json/accounting/v1/log', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ message, function: function_name, nonce, is_json: isJson }),
        });
    } else {
        console.log(function_name, message);
    }

};



export default Logger;