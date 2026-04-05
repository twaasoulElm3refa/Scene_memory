
هعمل صفحه اسمها احصائيات 
يختار منها الدوله ويظهر اللي هو عاوزه 
مرحله 2 مش الوقتي 


User clicks Pay
        ↓
Create Order (idempotent)
        ↓
Create Payment Session (gateway)
        ↓
Return iframe / redirect URL
        ↓
User pays
        ↓
Gateway processes payment
        ↓
Webhook sent to backend
        ↓
Verify webhook
        ↓
Update order status
        ↓
Success / Fail page (UI only)

