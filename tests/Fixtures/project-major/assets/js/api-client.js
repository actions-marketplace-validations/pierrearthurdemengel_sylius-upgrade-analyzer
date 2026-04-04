const API_BASE = '/api/v2';

function uploadAvatar(adminId, file) {
    return fetch(API_BASE + '/admin/avatar-images/', {
        method: 'POST',
        body: file
    });
}

function resetPassword(email) {
    return fetch('/api/v2/shop/reset-password-requests', {
        method: 'POST',
        body: JSON.stringify({ email: email })
    });
}

function verifyAccount(token) {
    return fetch(API_BASE + '/shop/account-verification-requests', {
        method: 'POST',
        body: JSON.stringify({ token: token })
    });
}

function getGatewayConfigs() {
    return fetch('/api/v2/admin/gateway-configs');
}

function getChannelPriceHistory(channelId) {
    return fetch('/api/v2/admin/channel-price-history-configs?channel=' + channelId);
}

function getShopBillingData(channelId) {
    return fetch('/api/v2/admin/shop-billing-datas?channel=' + channelId);
}

function getZoneMembers(zoneId) {
    return fetch('/api/v2/admin/zone-members?zone=' + zoneId);
}

function getOrderItemUnits(orderId) {
    return fetch('/api/v2/admin/order-item-units?order=' + orderId);
}
