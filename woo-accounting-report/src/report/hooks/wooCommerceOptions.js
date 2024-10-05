import api from '@wordpress/api';

const getSetting = async (name, defaultValue = undefined) => {

    try {
        await api.loadPromise;
        const settingsAPI = new api.models.Settings();
        const response = await settingsAPI.fetch();
        if (!response[name] || response[name] === '' || (Array.isArray(response[name]) && response[name].length === 0)) {
            return defaultValue;
        }
        return response[name];
    } catch (error) {
        console.error(error);
    }

    return false;

}

export default getSetting;