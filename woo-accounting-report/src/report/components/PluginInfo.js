import React from 'react';

const PluginInfo = () => {
    return (
        <div style={{ display: 'flex', justifyContent: 'flex-end', alignItems: 'center', flexDirection: 'column', marginRight: '20px', textAlign: 'center' }}>
            <div style={{ border: '1px solid lightgrey', backgroundColor: 'lightgrey', padding: '15px', borderRadius: '10px', boxShadow: '0 4px 8px rgba(0, 0, 0, 0.1)', display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
                <h1 style={{ fontWeight: 'bold', marginBottom: '10px' }}>Accounting Report</h1>
                <p>This report is designed and developed by <a href="https://bjorntech.com" style={{ textDecoration: 'underline' }}>BjornTech AB</a>, a provider of innovative solutions for e-commerce businesses. The primary objective of this report is to facilitate the extraction of crucial accounting data from WooCommerce, making it easier for you to manage your financial operations efficiently.  Please note that BjornTech AB is not responsible for any accounting errors caused by this report. You must verify the data yourself before using it.</p>
                <p style={{ fontWeight: 'bold' }}>This plugin is free of charge, and we hope it helps you streamline your accounting processes. Happy accounting!</p>
            </div>
        </div>
    );
}

export default PluginInfo;