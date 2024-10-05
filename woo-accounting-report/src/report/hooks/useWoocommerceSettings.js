import { useSelect } from '@wordpress/data';
import {
    OPTIONS_STORE_NAME,
    SETTINGS_STORE_NAME,
} from '@woocommerce/data';

const useWoocommerceSettings = () => {

    const { generalSettings, isResolving, taxSettings } = useSelect(
        (select) => {
            const { getSettings, hasFinishedResolution } = select(
                SETTINGS_STORE_NAME
            );
            return {
                generalSettings: getSettings('general').general,
                isResolving: !hasFinishedResolution('getSettings', ['general']),
                taxSettings: getSettings('tax').tax || {},
            };
        }
    );

    return { generalSettings, isResolving, taxSettings };
};

export default useWoocommerceSettings;