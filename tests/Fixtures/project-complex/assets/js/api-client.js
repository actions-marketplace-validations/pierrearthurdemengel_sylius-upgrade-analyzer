const API_BASE = '/api/v2';

function verifyAccount(token) {
    return fetch(API_BASE + '/shop/account-verification-requests', {
        method: 'POST',
        body: JSON.stringify({ token: token })
    });
}

function getOrderItemUnits(orderId) {
    return fetch('/api/v2/admin/order-item-units?order=' + orderId);
}
