const c="data:image/svg+xml;charset=UTF-8,"+encodeURIComponent(`
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 450">
            <defs>
                <linearGradient id="surface" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#eef6ff"/>
                    <stop offset="1" stop-color="#dce8f5"/>
                </linearGradient>
            </defs>
            <rect width="800" height="450" fill="url(#surface)"/>
            <circle cx="400" cy="196" r="58" fill="#ffffff" opacity=".9"/>
            <path d="M372 210l30-38 46 58h-96z" fill="#1677ff" opacity=".72"/>
            <circle cx="379" cy="187" r="10" fill="#0d4d97" opacity=".78"/>
            <rect x="322" y="275" width="156" height="12" rx="6" fill="#0d4d97" opacity=".2"/>
            <rect x="355" y="300" width="90" height="9" rx="4.5" fill="#0d4d97" opacity=".12"/>
        </svg>
    `),s=c;function n(){const r="https://dev.scemory.com";try{return new URL(r,window.location.origin).origin}catch{return String(r).replace(/\/+$/,"")}return["localhost","127.0.0.1"].includes(window.location.hostname)&&window.location.port&&window.location.port!=="8000"?`${window.location.protocol}//${window.location.hostname}:8000`:window.location.origin}function o(r){return r?typeof r=="string"?r:r.full_url||r.fullUrl||r.preview_url||r.previewUrl||r.image_url||r.imageUrl||r.webp_url||r.webpUrl||r.full_url_webp||r.fullUrlWebp||r.url||r.path||r.image||r.file_path||r.filePath||r.file||r.src||(typeof r.video=="string"?r.video:"")||"":""}function f(r){const i=o(r);if(!i||typeof i!="string")return s;const t=i.replace(/\\/g,"/").trim();if(!t)return s;if(/^https?:\/\//i.test(t))return t;if(t.startsWith("//"))return`${window.location.protocol}${t}`;const l=n();return t.startsWith("/storage/")||t.startsWith("/uploads/")?`${l}${t}`:t.startsWith("storage/")||t.startsWith("uploads/")?`${l}/${t}`:t.startsWith("public/")?`${l}/storage/${t.replace(/^public\//,"")}`:`${l}/storage/${t.replace(/^\/+/,"")}`}function a(r){if(!r||typeof r!="string")return!1;const i=r.split("?")[0].split("#")[0].toLowerCase();return[".mp4",".webm",".ogg",".mov",".m4v"].some(t=>i.endsWith(t))}function e(r){return r?String((r==null?void 0:r.type)||"").trim().toLowerCase()==="video"||r.video===!0||r.is_video===!0||r.isVideo===!0?!0:a(o(r)):!1}function u(r){var i;return o(r==null?void 0:r.first_image)||o(r==null?void 0:r.firstImage)||(r==null?void 0:r.image_webp_url)||(r==null?void 0:r.imageWebpUrl)||(r==null?void 0:r.image_url)||(r==null?void 0:r.imageUrl)||o((i=r==null?void 0:r.images)==null?void 0:i[0])||(r==null?void 0:r.image)||""}function p(r){return f(u(r))}function g(r,i="en"){if(!r)return"—";const t=new Date(r);if(Number.isNaN(t.getTime()))return"—";try{return new Intl.DateTimeFormat(i,{day:"numeric",month:"short",year:"numeric"}).format(t)}catch{return new Intl.DateTimeFormat("en",{day:"numeric",month:"short",year:"numeric"}).format(t)}}function w(r){const i=r==null?void 0:r.target;!i||i.dataset.fallbackApplied==="1"||(i.dataset.fallbackApplied="1",i.src=s)}export{o as a,w as b,p as c,g as f,f as g,e as i};
