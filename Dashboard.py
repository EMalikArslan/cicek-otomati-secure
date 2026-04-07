import streamlit as st
import firebase_admin
from firebase_admin import credentials, db, storage, auth
import time
from datetime import datetime, timedelta
import pandas as pd
import plotly.express as px
from PIL import Image as PILImage, ImageOps
import io
import requests
import json

# ====================================================================
# 1. AYARLAR VE GÜVENLİK
# ====================================================================
st.set_page_config(
    page_title="Açelya Çiçekçilik & ETM",
    page_icon="🌸",
    layout="wide",
    initial_sidebar_state="collapsed"
)

try:
    ADMIN_EMAIL = st.secrets["ADMIN_EMAIL"]
    FIREBASE_WEB_API_KEY = st.secrets["FIREBASE_WEB_API_KEY"]
    STORAGE_BUCKET_NAME = st.secrets["STORAGE_BUCKET_NAME"]
    DB_URL = st.secrets["DB_URL"]
except KeyError as e:
    st.error(f"HATA: Streamlit Secrets eksik: {e}")
    st.stop()

if not firebase_admin._apps:
    try:
        if "textkey" in st.secrets:
            key_dict = json.loads(st.secrets["textkey"])
            cred = credentials.Certificate(key_dict)
            firebase_admin.initialize_app(cred, {
                'databaseURL': DB_URL,
                'storageBucket': STORAGE_BUCKET_NAME
            })
    except Exception as e:
        st.error(f"Firebase Bağlantı Hatası: {e}")
        st.stop()

# ====================================================================
# 2. YARDIMCI FONKSİYONLAR
# ====================================================================
def auth_request(endpoint, email, password=None, return_secure_token=True):
    try:
        url = f"https://identitytoolkit.googleapis.com/v1/accounts:{endpoint}?key={FIREBASE_WEB_API_KEY}"
        headers = {"Content-Type": "application/json"}
        if endpoint == "sendOobCode": data = {"requestType": "PASSWORD_RESET", "email": email}
        else: data = {"email": email, "password": password, "returnSecureToken": return_secure_token}
        return requests.post(url, headers=headers, json=data).json()
    except: return None

def change_user_password_admin_sdk(uid, new_password):
    try: auth.update_user(uid, password=new_password); return True, "Şifre güncellendi."
    except Exception as e: return False, f"Hata: {str(e)}"

def verify_old_password(email, old_password):
    res = auth_request("signInWithPassword", email, old_password)
    return True if res and "localId" in res else False

def create_user_db_entry(uid, email, full_name, kvkk_approved):
    try:
        db.reference(f'users/{uid}').set({
            "email": email, "full_name": full_name, "approved": False, "machines": ["DEMO"],
            "created_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            "kvkk_consent": kvkk_approved, "kvkk_consent_date": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        })
        return True
    except: return False

def get_user_data(uid): return db.reference(f'users/{uid}').get()
def get_all_users(): return db.reference('users').get()
def get_all_machines(): return list(db.reference('machines').get().keys()) if db.reference('machines').get() else []
def update_user_status(uid, approved, machines): db.reference(f'users/{uid}').update({"approved": approved, "machines": machines})
def get_machine_status(mid): return db.reference(f'machines/{mid}/info').get()
def get_slots(mid): 
    data = db.reference(f'machines/{mid}/slots').get()
    if isinstance(data, list): return {str(i): item for i, item in enumerate(data) if item}
    return data if data else {}

def get_sales_history(mid):
    try:
        data = db.reference(f'machines/{mid}/satis_hareketleri').get()
        if not data: return None
        if isinstance(data, dict): sales = list(data.values())
        else: sales = data
        df = pd.DataFrame(sales)
        if df.empty: return None
        # Kolon isimlerini düzeltme
        cols = {c.lower(): c for c in df.columns}
        if 'tarih' in cols: df['Tarih'] = pd.to_datetime(df[cols['tarih']])
        if 'fiyat' in cols: df['Tutar'] = df[cols['fiyat']]
        if 'kutu' in cols: df['Kutu'] = df[cols['kutu']]
        if 'urun' in cols: df['Ürün'] = df[cols['urun']]
        return df
    except: return None

def send_open_command(mid, sid):
    try:
        db.reference(f'machines/{mid}/commands').update({"open_gate": str(sid), "timestamp": time.time()})
        st.toast(f"🚪 {sid}. Kapak Sinyali Gönderildi!", icon="✅")
    except Exception as e: st.error(f"Hata: {e}")

def update_slot(mid, sid, price, enabled):
    db.reference(f'machines/{mid}/slots/{sid}').update({"price": price, "enabled": enabled})

def update_product_info(mid, sid, name, price, url):
    data = {"price": price, "product_name": name, "enabled": True, "last_restock": datetime.now().strftime("%Y-%m-%d %H:%M:%S")}
    if url: data["image_url"] = url
    db.reference(f'machines/{mid}/slots/{sid}').update(data)

def upload_image_to_firebase(image_file, mid, sid):
    try:
        image = PILImage.open(image_file)
        image = ImageOps.exif_transpose(image)
        if image.mode in ("RGBA", "P"): image = image.convert("RGB")
        image.thumbnail((500, 500)) 
        img_byte_arr = io.BytesIO()
        image.save(img_byte_arr, format='JPEG', quality=70)
        img_byte_arr = img_byte_arr.getvalue()
        
        bucket = storage.bucket(name=STORAGE_BUCKET_NAME)
        blob_path = f"machines/{mid}/current_slot_{sid}.jpg"
        blob = bucket.blob(blob_path)
        blob.upload_from_string(img_byte_arr, content_type='image/jpeg')
        
        # Public yapmayı dene, olmazsa manuel link ver
        try:
            blob.make_public()
            return blob.public_url
        except:
            return f"https://storage.googleapis.com/{STORAGE_BUCKET_NAME}/{blob_path}"
    except Exception as e:
        st.error(f"Resim Hatası: {e}"); return None

# --- YARDIMCI FONKSİYONLAR ---
def initialize_machine_slots(mid, slot_count):
    try:
        slots_data = {}
        for i in range(1, slot_count + 1):
            slots_data[str(i)] = {"product_name": f"Raf {i}", "price": 0, "enabled": False, "image_url": "", "last_restock": ""}
        db.reference(f'machines/{mid}/slots').set(slots_data)
        return True
    except: return False

def send_feedback(uid, email, name, subject, message):
    try:
        db.reference('feedbacks').push({
            "user_id": uid, "email": email, "user_name": name, "subject": subject,
            "message": message, "status": "pending", "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S"), "admin_reply": ""
        })
        return True
    except: return False

def get_feedbacks(user_id=None):
    try:
        data = db.reference('feedbacks').get()
        if not data: return []
        feedbacks = []
        for fid, val in data.items():
            val['id'] = fid 
            if user_id:
                if val.get('user_id') == user_id: feedbacks.append(val)
            else: feedbacks.append(val)
        feedbacks.sort(key=lambda x: x.get('timestamp', ''), reverse=True)
        return feedbacks
    except: return []

def reply_to_feedback(feedback_id, reply_message):
    try:
        db.reference(f'feedbacks/{feedback_id}').update({
            "admin_reply": reply_message, "status": "replied", "reply_timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        })
        return True
    except: return False

def delete_feedback(feedback_id):
    try: db.reference(f'feedbacks/{feedback_id}').delete(); return True
    except: return False

def nav_to_machine(mid):
    st.session_state['sb_menu'] = "Makine Yönetimi"
    st.session_state['selected_machine'] = mid

# ====================================================================
# 3. PUBLIC WEBSITE & LOGIN
# ====================================================================
def public_website():
    # --- NAV BAR ---
    st.markdown("""
    <style>
    .navbar {
        display: flex; justify-content: space-between; align-items: center;
        padding: 12px 32px; background: linear-gradient(90deg, #1a001a 0%, #3d0030 100%);
        border-radius: 14px; margin-bottom: 8px;
    }
    .navbar-brand { color: #ff69b4; font-size: 1.5rem; font-weight: 800; letter-spacing: 1px; }
    .hero {
        text-align: center; padding: 70px 20px 50px 20px;
        background: linear-gradient(135deg, #fdf2f8 0%, #fff0f5 50%, #fce4ec 100%);
        border-radius: 24px; margin-bottom: 32px;
    }
    .hero h1 { color: #D9007E; font-size: 2.8rem; font-weight: 900; margin-bottom: 12px; }
    .hero p { color: #555; font-size: 1.2rem; max-width: 650px; margin: 0 auto 24px auto; }
    .badge {
        display: inline-block; background: #D9007E; color: white;
        border-radius: 20px; padding: 4px 16px; font-size: 0.85rem; margin: 4px;
    }
    .section-title { color: #D9007E; font-size: 1.9rem; font-weight: 800; margin-bottom: 6px; }
    .section-sub { color: #777; margin-bottom: 28px; font-size: 1.05rem; }
    .feature-card {
        background: white; border-radius: 16px; padding: 28px 20px; text-align: center;
        border: 1.5px solid #f8d7e8; height: 100%;
        box-shadow: 0 2px 12px rgba(217,0,126,0.07);
    }
    .feature-icon { font-size: 2.4rem; margin-bottom: 12px; }
    .feature-title { font-weight: 700; color: #1a001a; font-size: 1.05rem; margin-bottom: 8px; }
    .feature-desc { color: #666; font-size: 0.93rem; line-height: 1.6; }
    .model-card {
        background: white; border-radius: 18px; padding: 22px 18px;
        border: 1.5px solid #f0d6e8; margin-bottom: 16px;
        box-shadow: 0 2px 14px rgba(217,0,126,0.06);
    }
    .model-name { color: #D9007E; font-size: 1.1rem; font-weight: 800; margin-bottom: 6px; }
    .model-tag {
        display: inline-block; background: #fdf2f8; color: #D9007E;
        border: 1px solid #f8b4d4; border-radius: 12px;
        padding: 2px 10px; font-size: 0.78rem; margin: 2px;
    }
    .model-spec { color: #555; font-size: 0.9rem; margin-top: 8px; line-height: 1.7; }
    .contact-card {
        background: linear-gradient(135deg, #1a001a, #3d0030);
        border-radius: 20px; padding: 40px 32px; color: white; text-align: center;
    }
    .contact-card h2 { color: #ff69b4; margin-bottom: 12px; }
    .contact-info { font-size: 1.1rem; margin: 8px 0; }
    .divider-pink { border: none; border-top: 2px solid #f8d7e8; margin: 40px 0; }
    </style>
    <div class="navbar">
        <span class="navbar-brand">🌸 Açelya & ETM</span>
        <span style="color:#ccc; font-size:0.9rem;">7/24 Akıllı Çiçek Otomatı Sistemleri</span>
    </div>
    """, unsafe_allow_html=True)

    col_nav, col_btn = st.columns([5, 1])
    with col_btn:
        if st.button("🔐 Bayi Girişi", type="primary", use_container_width=True):
            st.session_state['show_login'] = True; st.rerun()

    if st.session_state.get('show_login'): login_section(); return

    tabs = st.tabs(["🏠 Ana Sayfa", "🚀 Özellikler", "🤖 Modellerimiz", "📞 İletişim"])

    # ================================================================
    # TAB 1 - ANA SAYFA
    # ================================================================
    with tabs[0]:
        st.markdown("""
        <div class="hero">
            <div><span class="badge">🆕 Yeni Nesil</span> <span class="badge">🇹🇷 Yerli Üretim</span> <span class="badge">🔧 IoT Bağlantılı</span></div>
            <br>
            <h1>Çiçekçilikte Yeni Bir Çağ</h1>
            <p>ETM Akıllı Çiçek Otomatları ile işletmenizi 7 gün 24 saat çalıştırın. Personel gideri olmadan, her lokasyonda, her koşulda satış yapın.</p>
            <div>
                <span class="badge">✅ Temassız Ödeme</span>
                <span class="badge">📱 Uzaktan Yönetim</span>
                <span class="badge">❄️ İklim Kontrolü</span>
                <span class="badge">📊 Anlık Raporlama</span>
            </div>
        </div>
        """, unsafe_allow_html=True)

        c1, c2, c3, c4 = st.columns(4)
        c1.metric("Aktif Model", "9", "Farklı tasarım")
        c2.metric("Max Kapasite", "96+", "Raf/gözlem")
        c3.metric("Çalışma Süresi", "7/24", "Kesintisiz")
        c4.metric("Geri Ödeme", "~12 Ay", "Ortalama")

        st.markdown("<hr class='divider-pink'>", unsafe_allow_html=True)
        st.markdown("""
        <div style="text-align:center">
            <div class="section-title">Neden ETM Çiçek Otomatı?</div>
            <div class="section-sub">Rakiplerinizden bir adım önde olun</div>
        </div>
        """, unsafe_allow_html=True)

        col1, col2, col3 = st.columns(3)
        with col1:
            st.markdown("""<div class="feature-card">
                <div class="feature-icon">💰</div>
                <div class="feature-title">Düşük İşletme Maliyeti</div>
                <div class="feature-desc">Personel gideri olmadan çalışır. Elektrik tüketimi minimumda tutulmuştur. Bakım gereksinimleri oldukça düşüktür.</div>
            </div>""", unsafe_allow_html=True)
        with col2:
            st.markdown("""<div class="feature-card">
                <div class="feature-icon">📍</div>
                <div class="feature-title">Esnek Yerleşim</div>
                <div class="feature-desc">AVM, hastane, otel, havalimanı, metro istasyonu — 9 farklı form faktörüyle her mekâna özel çözüm.</div>
            </div>""", unsafe_allow_html=True)
        with col3:
            st.markdown("""<div class="feature-card">
                <div class="feature-icon">📲</div>
                <div class="feature-title">Tam Uzaktan Kontrol</div>
                <div class="feature-desc">Fiyat güncelleme, stok takibi, kapak kontrolü, sıcaklık izleme — her şey telefonunuzdan.</div>
            </div>""", unsafe_allow_html=True)

    # ================================================================
    # TAB 2 - ÖZELLİKLER
    # ================================================================
    with tabs[1]:
        st.markdown("""
        <div class="section-title">🚀 Teknik Özellikler</div>
        <div class="section-sub">ETM sistemlerinin size sunduğu teknolojik avantajlar</div>
        """, unsafe_allow_html=True)

        col1, col2 = st.columns(2)
        with col1:
            features_left = [
                ("🔌", "IoT / Firebase Bağlantısı", "Raspberry Pi tabanlı kontrol birimi ile gerçek zamanlı Firebase bağlantısı. İnternet kesintilerinde yerel mod devreye girer."),
                ("💳", "Çoklu Ödeme Desteği", "Entegre POS terminali ile kredi kartı, banka kartı ve temassız ödeme (NFC) desteği. Nakit modülü opsiyoneldir."),
                ("❄️", "İklim Kontrol Sistemi", "Çiçeklerin tazeliğini korumak için dahili sıcaklık ve nem sensörü. Kritik değerlerde anlık alarm bildirimi."),
                ("📸", "Ürün Fotoğraf Yönetimi", "Her gözlem için fotoğraf yüklenebilir. Müşteri dokunmatik ekranda ürünü görerek seçer."),
            ]
            for icon, title, desc in features_left:
                st.markdown(f"""<div class="feature-card" style="margin-bottom:16px; text-align:left; display:flex; gap:16px; align-items:flex-start; padding:20px;">
                    <div style="font-size:2rem">{icon}</div>
                    <div><div class="feature-title">{title}</div><div class="feature-desc">{desc}</div></div>
                </div>""", unsafe_allow_html=True)

        with col2:
            features_right = [
                ("🖥️", "Dokunmatik Kiosk Ekran", "Müşteri arayüzü için 10\"+ dokunmatik ekran. Ürün görseli, fiyat ve seçim işlemi kolayca yapılır."),
                ("📊", "Gelişmiş Satış Analitiği", "Saatlik yoğunluk grafikleri, en çok satan ürünler, günlük/aylık ciro takibi. Web panelinden anlık erişim."),
                ("🔒", "Çok Katmanlı Güvenlik", "Bayi bazlı yetkilendirme, Firebase Authentication, şifreli iletişim. Her bayi yalnızca kendi makinelerini görür."),
                ("🛠️", "Akıllı Dolum Asistanı", "Hangi rafın doldurulduğunu seçin, ürün adı ve fiyatını girin, fotoğraf çekin — sistem otomatik günceller."),
            ]
            for icon, title, desc in features_right:
                st.markdown(f"""<div class="feature-card" style="margin-bottom:16px; text-align:left; display:flex; gap:16px; align-items:flex-start; padding:20px;">
                    <div style="font-size:2rem">{icon}</div>
                    <div><div class="feature-title">{title}</div><div class="feature-desc">{desc}</div></div>
                </div>""", unsafe_allow_html=True)

        st.markdown("<hr class='divider-pink'>", unsafe_allow_html=True)
        st.markdown("""<div class="section-title">⚙️ Teknik Detaylar</div>""", unsafe_allow_html=True)
        specs = {
            "İşlemci": "Raspberry Pi 4 Model B (4GB RAM)",
            "Bağlantı": "Wi-Fi 802.11ac / Ethernet (LAN)",
            "Ödeme Sistemi": "Entegre POS Terminali (Visa, Mastercard, Temassız)",
            "Yazılım": "Python / Firebase Realtime DB / Streamlit Web Panel",
            "Uzaktan Yönetim": "Web tarayıcısı üzerinden, internet bağlantısı olan her cihazdan",
            "Gövde Malzemesi": "Yüksek dayanımlı çelik + temperli cam",
            "Güç Tüketimi": "Modele göre 150W – 400W",
            "Garanti": "1 Yıl Parça & İşçilik",
        }
        col_a, col_b = st.columns(2)
        items = list(specs.items())
        for i, (k, v) in enumerate(items):
            target = col_a if i < 4 else col_b
            with target:
                st.markdown(f"**{k}:** {v}")

    # ================================================================
    # TAB 3 - MODELLERİMİZ
    # ================================================================
    with tabs[2]:
        st.markdown("""
        <div class="section-title">🤖 Modellerimiz</div>
        <div class="section-sub">Her mekân ve ihtiyaca özel 9 farklı tasarım</div>
        """, unsafe_allow_html=True)

        models = [
            {
                "isim": "Model 1 — Duvar Tipi Dikdörtgen",
                "etiketler": ["Duvar Tipi", "20 Gözlü", "Dar Alan"],
                "aciklama": "Klasik dikdörtgen gövde, düz kanopi tepesi. 4 sütun × 5 satır düzeninde 20 bağımsız cam kapılı gözlem. Duvar önü yerleşim için idealdir.",
                "detaylar": [
                    ("📦", "Kapasite", "20 gözlem (4×5)"),
                    ("📐", "Yerleşim", "Duvar önü / iç mekân"),
                    ("🚪", "Kapak Tipi", "Bireysel cam kapılı"),
                    ("💡", "Öne Çıkan", "Kompakt, yüksek gözlem yoğunluğu"),
                ],
            },
            {
                "isim": "Model 2 — Geniş Duvar Tipi",
                "etiketler": ["Duvar Tipi", "12 Büyük Gözlü", "Geniş Ürün"],
                "aciklama": "Daha büyük gözlemlerle tasarlanmış geniş duvar tipi. 3 sütun × 4 satır düzeninde 12 büyük gözlem. Büket gibi hacimli çiçek aranjmanları için uygundur.",
                "detaylar": [
                    ("📦", "Kapasite", "12 gözlem (3×4)"),
                    ("📐", "Yerleşim", "Duvar önü / geniş koridor"),
                    ("🚪", "Kapak Tipi", "Büyük cam kapılı"),
                    ("💡", "Öne Çıkan", "Büyük ambalajlı ürünler için ideal"),
                ],
            },
            {
                "isim": "Model 2 V2 — Köşe Tipi Yarım Yuvarlak",
                "etiketler": ["Köşe Tipi", "Yarım Yuvarlak", "Estetik"],
                "aciklama": "Yarım yuvarlak kanopi ve çokgen gövdesiyle köşe yerleşimine özel tasarım. Mekânın köşelerini değerlendirerek satış alanı oluşturur.",
                "detaylar": [
                    ("📦", "Kapasite", "9–12 gözlem"),
                    ("📐", "Yerleşim", "Köşe / L köşe"),
                    ("🚪", "Kapak Tipi", "Cam vitrin raflı"),
                    ("💡", "Öne Çıkan", "Ölü köşeleri değerlendirir"),
                ],
            },
            {
                "isim": "Model 2 V3 — L Tipi Tezgah",
                "etiketler": ["Tezgah Tipi", "L Şekil", "Karşılama Noktası"],
                "aciklama": "L şekilli tezgah tasarımı, resepsiyon veya mağaza girişi için idealdir. Düz açılı kanopi ve geniş raf alanı ile profesyonel bir görünüm sunar.",
                "detaylar": [
                    ("📦", "Kapasite", "8–16 gözlem"),
                    ("📐", "Yerleşim", "Resepsiyon / giriş noktası"),
                    ("🚪", "Kapak Tipi", "Açık raf / cam kapılı"),
                    ("💡", "Öne Çıkan", "Karşılama noktası tasarımı"),
                ],
            },
            {
                "isim": "Model 2 V4 — Kompakt Dikdörtgen",
                "etiketler": ["Kompakt", "12 Gözlü", "Çok Yönlü"],
                "aciklama": "Model 2'nin daha kompakt ve hafif versiyonu. Düz kanopi altında 3×4 gözlem düzeni. Sınırlı alanlarda tam performans sunar.",
                "detaylar": [
                    ("📦", "Kapasite", "12 gözlem (3×4)"),
                    ("📐", "Yerleşim", "İç mekân / dar koridor"),
                    ("🚪", "Kapak Tipi", "Cam kapılı"),
                    ("💡", "Öne Çıkan", "Taşınabilir, çok yönlü"),
                ],
            },
            {
                "isim": "Model 3 — Dairesel 360°",
                "etiketler": ["Dairesel", "360° Erişim", "Merkez Yerleşim"],
                "aciklama": "Oktagonal gövde ve dairesel kanopi ile 360° erişim sağlayan model. Müşteriler her yönden ürünleri görebilir. AVM orta koridoru gibi açık alanlara uygundur.",
                "detaylar": [
                    ("📦", "Kapasite", "16–24 gözlem"),
                    ("📐", "Yerleşim", "Orta koridor / açık alan"),
                    ("🚪", "Kapak Tipi", "Çevre yönlü cam kapılı"),
                    ("💡", "Öne Çıkan", "Her açıdan görünür, yüksek dikkat çekme"),
                ],
            },
            {
                "isim": "Model 4 Altıgen — Altıgen Kule",
                "etiketler": ["Kule Tipi", "Altıgen", "Yüksek Kapasite"],
                "aciklama": "Yüksek altıgen sütun formu ve kubbe tepesiyle dikkat çekici bir kule tasarımı. Her yüzeyinde raf sistemi barındırır. Yüksek hacimli lokasyonlar için uygundur.",
                "detaylar": [
                    ("📦", "Kapasite", "30–48 gözlem"),
                    ("📐", "Yerleşim", "Büyük AVM / havalimanı"),
                    ("🚪", "Kapak Tipi", "Yüzey bazlı cam kapılı"),
                    ("💡", "Öne Çıkan", "Maksimum kapasite ve görünürlük"),
                ],
            },
            {
                "isim": "Model 4 Piramit — Piramit Tepeli Oktagon",
                "etiketler": ["Oktagonal", "Piramit Tepe", "Premium"],
                "aciklama": "Yarı kubbe/piramit tepeli ve oktagonal gövdeli premium tasarım. 4 sıra raf × çok yüzey = yüksek kapasite. Estetik görünümüyle lüks mekânlara hitap eder.",
                "detaylar": [
                    ("📦", "Kapasite", "24–40 gözlem"),
                    ("📐", "Yerleşim", "Otel / lüks AVM"),
                    ("🚪", "Kapak Tipi", "Çevre yönlü cam kapılı"),
                    ("💡", "Öne Çıkan", "Premium estetik, yüksek kapasite"),
                ],
            },
            {
                "isim": "Model 4 Sekizgen — Sekizgen Kule",
                "etiketler": ["Sekizgen", "Kule", "Çok Yönlü"],
                "aciklama": "Yuvarlak düz tepeli sekizgen kule. Her yüzeyinde dikey dizilmiş çoklu gözlemler. Maksimum raf yoğunluğu ve 360° müşteri erişimi ile en yüksek kapasiteli model.",
                "detaylar": [
                    ("📦", "Kapasite", "48–96+ gözlem"),
                    ("📐", "Yerleşim", "Büyük alan / merkezi nokta"),
                    ("🚪", "Kapak Tipi", "Yüzey bazlı çoklu kapılı"),
                    ("💡", "Öne Çıkan", "En yüksek kapasite, tam 360° erişim"),
                ],
            },
        ]

        for i in range(0, len(models), 2):
            cols = st.columns(2)
            for j, col in enumerate(cols):
                if i + j >= len(models): break
                m = models[i + j]
                with col:
                    tags_html = "".join([f'<span class="model-tag">{t}</span>' for t in m["etiketler"]])
                    specs_html = "".join([
                        f'<div style="display:flex;gap:8px;align-items:center;margin:3px 0">'
                        f'<span style="font-size:1rem">{ic}</span>'
                        f'<span style="color:#888;font-size:0.85rem;min-width:80px">{lbl}</span>'
                        f'<span style="font-weight:600;font-size:0.9rem;color:#1a001a">{val}</span></div>'
                        for ic, lbl, val in m["detaylar"]
                    ])
                    st.markdown(f"""<div class="model-card">
                        <div class="model-name">{m["isim"]}</div>
                        <div style="margin-bottom:10px">{tags_html}</div>
                        <div class="model-spec">{m["aciklama"]}</div>
                        <div style="margin-top:14px; padding-top:12px; border-top: 1px solid #f8d7e8">{specs_html}</div>
                    </div>""", unsafe_allow_html=True)

    # ================================================================
    # TAB 4 - İLETİŞİM
    # ================================================================
    with tabs[3]:
        st.markdown("""
        <div class="section-title">📞 İletişim</div>
        <div class="section-sub">Bayi olmak, teklif almak veya demo talep etmek için bize ulaşın</div>
        """, unsafe_allow_html=True)

        col1, col2 = st.columns([1, 1])
        with col1:
            st.markdown("""<div class="contact-card">
                <h2>🌸 Açelya Çiçekçilik & ETM</h2>
                <p style="color:#ddd; margin-bottom:24px">7/24 Akıllı Çiçek Otomatı Sistemleri</p>
                <div class="contact-info">📧 <a href="mailto:ensmlk.68@gmail.com" style="color:#ff69b4">ensmlk.68@gmail.com</a></div>
                <div class="contact-info">🕐 Yanıt Süresi: En geç 24 saat</div>
                <div class="contact-info">🇹🇷 Türkiye geneli bayi ağı</div>
                <br>
                <div style="background:rgba(255,255,255,0.08); border-radius:12px; padding:16px; margin-top:8px">
                    <div style="color:#ff69b4; font-weight:700; margin-bottom:8px">📋 Demo Talebi</div>
                    <div style="color:#ddd; font-size:0.9rem">Yukarıdaki mail adresine <b>\"Demo Talebi\"</b> konusuyla yazın. En yakın lokasyonda canlı demo ayarlayalım.</div>
                </div>
            </div>""", unsafe_allow_html=True)

        with col2:
            st.markdown("#### ✉️ Bize Yazın")
            with st.form("public_contact"):
                name = st.text_input("Ad Soyad / Firma")
                email_c = st.text_input("E-Posta Adresiniz")
                interest = st.selectbox("İlgilendiğiniz Konu", [
                    "Bayi Olmak İstiyorum",
                    "Fiyat Teklifi",
                    "Demo Talep Ediyorum",
                    "Teknik Bilgi",
                    "Diğer"
                ])
                msg = st.text_area("Mesajınız", height=120)
                if st.form_submit_button("📨 Gönder", type="primary", use_container_width=True):
                    if name and email_c and msg:
                        try:
                            db.reference('iletisim_formu').push({
                                "ad": name, "email": email_c, "konu": interest,
                                "mesaj": msg, "tarih": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                            })
                            st.success("Mesajınız iletildi! En kısa sürede dönüş yapacağız. 🌸")
                        except:
                            st.info(f"Mesajınız için teşekkürler! Bize **ensmlk.68@gmail.com** adresinden de yazabilirsiniz.")
                    else:
                        st.warning("Lütfen tüm alanları doldurun.")

def login_section():
    st.markdown("<br><br>", unsafe_allow_html=True)
    col1, col2, col3 = st.columns([1,1,1])
    with col2:
        st.markdown("## 🔐 Bayi Girişi")
        if st.button("⬅️ Web Sitesine Dön"): st.session_state['show_login'] = False; st.rerun()
        t1, t2, t3 = st.tabs(["Giriş Yap", "Başvuru Yap", "Şifremi Unuttum"])
        with t1:
            with st.form("login"):
                email = st.text_input("E-Posta"); pw = st.text_input("Şifre", type="password")
                if st.form_submit_button("Giriş Yap", type="primary"):
                    res = auth_request("signInWithPassword", email, pw)
                    if res and "localId" in res:
                        st.session_state['logged_in'] = True; st.session_state['user_uid'] = res["localId"]; st.session_state['user_email'] = email
                        if email == ADMIN_EMAIL: st.session_state['user_name'] = "Yönetici"; st.session_state['is_admin'] = True; st.session_state['machines'] = get_all_machines() or ["ETM_001"]
                        else:
                            u_data = get_user_data(res["localId"])
                            if u_data and u_data.get("approved"): st.session_state['user_name'] = u_data.get("full_name"); st.session_state['is_admin'] = False; st.session_state['machines'] = u_data.get("machines", [])
                            else: st.warning("Hesap onaylanmadı."); return
                        st.rerun()
                    else: st.error("Hatalı Giriş")
        with t2:
            with st.form("reg"):
                n = st.text_input("Firma"); e = st.text_input("E-Posta"); p = st.text_input("Şifre", type="password"); k = st.checkbox("KVKK Onay")
                if st.form_submit_button("Başvuru") and k:
                    res = auth_request("signUp", e, p)
                    if res and "localId" in res: create_user_db_entry(res["localId"], e, n, True); st.success("Başvuru Alındı!")
                    else: st.error("Hata")
        with t3:
            with st.form("reset"):
                m = st.text_input("E-Posta")
                if st.form_submit_button("Sıfırla") and m: auth_request("sendOobCode", m); st.success("Mail gönderildi!")

# ====================================================================
# 5. DASHBOARD
# ====================================================================
def dashboard_content():
    if 'sb_menu' not in st.session_state: st.session_state['sb_menu'] = "Genel Bakış"
    menu_items = ["Genel Bakış", "Makine Yönetimi"]
    if st.session_state.get('is_admin'): menu_items.extend(["Kullanıcılar", "Geri Bildirimler"])
    menu_items.extend(["Destek & İletişim", "Hesabım"])

    with st.sidebar:
        st.title("🎛️ ETM Panel")
        st.write(f"Sn, {st.session_state.get('user_name')}")
        menu = st.radio("Menü", menu_items, key="sb_menu")
        if st.button("Çıkış Yap", type="primary"): st.session_state.clear(); st.rerun()

    if menu == "Genel Bakış":
        machines = st.session_state.get('machines', [])
        st.subheader("📊 Makinelerim")
        if not machines: st.info("Makine yok."); return
        for mid in machines:
            info = get_machine_status(mid) or {}
            is_online = False
            if info.get('online_status') and info.get('last_seen'):
                try:
                    last_seen = datetime.strptime(info['last_seen'], "%Y-%m-%d %H:%M:%S")
                    diff = abs((datetime.utcnow() + timedelta(hours=3) - last_seen).total_seconds())
                    is_online = diff < 300
                except: pass
            with st.container(border=True):
                c1, c2, c3, c4 = st.columns(4)
                c1.markdown(f"**{mid}**")
                c1.caption(f"📍 {info.get('location', '---')}")
                c2.metric("Sıcaklık", f"{info.get('temperature', 0)} °C")
                c3.metric("Durum", "🟢 Çevrimiçi" if is_online else "🔴 Çevrimdışı")
                c4.write("")
                c4.button("⚙️ Yönet", key=f"go_{mid}", on_click=nav_to_machine, args=(mid,), type="primary", use_container_width=True)

    elif menu == "Makine Yönetimi":
        machines = st.session_state.get('machines', [])
        if not machines: st.warning("Makine yok."); return
        mid = st.session_state.get('selected_machine', machines[0])
        mid = st.selectbox("Makine Seç", machines, index=machines.index(mid) if mid in machines else 0)
        st.session_state['selected_machine'] = mid
        st.header(f"🔧 Yönetim: {mid}")
        
        slots = get_slots(mid)
        if not slots:
            st.warning("Bu makine boş."); sc = st.number_input("Raf Sayısı", 1, 100, 31)
            if st.button("Kurulumu Başlat"): initialize_machine_slots(mid, sc); st.rerun()
            return 

        sorted_ids = sorted(slots.keys(), key=lambda x: int(x) if x.isdigit() else 999)
        tab1, tab2, tab3, tab4 = st.tabs(["💰 Fiyat & Stok", "🎮 Kapak Kontrol", "📊 Satış Raporu", "🌷 Akıllı Dolum"])

        # --- TAB 1 ---
        with tab1:
            with st.form("bulk"):
                save_top = st.form_submit_button("💾 Tümünü Kaydet", type="primary", use_container_width=True, key="save_top")
                st.divider()
                cols = st.columns(2)
                for i, sid in enumerate(sorted_ids):
                    data = slots[sid]
                    with cols[i % 2]:
                        with st.container(border=True):
                            c1, c2 = st.columns([1, 2])
                            with c1:
                                if data.get('image_url'): st.image(data['image_url'], use_container_width=True)
                                else: st.caption("📷 Fotoğraf yok")
                            with c2:
                                st.markdown(f"##### Raf {sid}")
                                st.write(f"**{data.get('product_name', '—')}**")
                                st.number_input("Fiyat (₺)", min_value=0, value=int(data.get('price', 0)),
                                                key=f"p_{sid}_{mid}", label_visibility="collapsed")
                                st.checkbox("Satışta", value=data.get('enabled', False), key=f"e_{sid}_{mid}")
                st.divider()
                save_bot = st.form_submit_button("💾 Tümünü Kaydet", type="primary", use_container_width=True, key="save_bot")
                if save_top or save_bot:
                    bar = st.progress(0, text="Kaydediliyor...")
                    for i, sid in enumerate(sorted_ids):
                        update_slot(mid, sid, st.session_state.get(f"p_{sid}_{mid}"), st.session_state.get(f"e_{sid}_{mid}"))
                        bar.progress((i + 1) / len(sorted_ids))
                    st.success("Kaydedildi!"); time.sleep(1); st.rerun()

        # --- TAB 2 (DÜZELTİLDİ: SIRALI DİZİLİM) ---
        with tab2:
            st.info("Kapak açmak için butona basınız.")
            cols = st.columns(4)
            # Raf ID'lerini sayısal olarak sırala
            sorted_slots = sorted(slots.keys(), key=lambda x: int(x) if x.isdigit() else 999)
            
            for i, sid in enumerate(sorted_slots):
                # i indexine göre sırayla kolonlara yerleştir: 0, 1, 2, 3, 0, 1...
                col_index = i % 4
                with cols[col_index]:
                    if st.button(f"Raf {sid} AÇ", key=f"op_{sid}", use_container_width=True):
                        send_open_command(mid, sid)

        with tab3:
            st.subheader("📊 Satış Analizi")
            df = get_sales_history(mid)
            if df is not None and not df.empty and 'Tarih' in df.columns and 'Tutar' in df.columns:
                now = datetime.now()
                today = df[df['Tarih'] >= now.replace(hour=0, minute=0, second=0, microsecond=0)]
                month = df[df['Tarih'] >= now.replace(day=1, hour=0, minute=0, second=0, microsecond=0)]
                c1, c2, c3, c4 = st.columns(4)
                c1.metric("Bugün Ciro", f"{today['Tutar'].sum():.0f} ₺", f"{len(today)} satış")
                c2.metric("Bu Ay Ciro", f"{month['Tutar'].sum():.0f} ₺", f"{len(month)} satış")
                c3.metric("Toplam Ciro", f"{df['Tutar'].sum():.0f} ₺", f"{len(df)} satış")
                c4.metric("Ortalama Sepet", f"{df['Tutar'].mean():.0f} ₺")
                st.divider()
                ch1, ch2 = st.columns(2)
                with ch1:
                    st.markdown("##### 🕒 Saatlik Yoğunluk")
                    df['Saat'] = df['Tarih'].dt.hour
                    full_hours = pd.DataFrame({'Saat': range(24)})
                    hourly = df.groupby('Saat').size().reset_index(name='Adet')
                    final_h = pd.merge(full_hours, hourly, on='Saat', how='left').fillna(0)
                    st.plotly_chart(px.bar(final_h, x='Saat', y='Adet', text='Adet',
                                           color_discrete_sequence=['#D9007E']), use_container_width=True)
                with ch2:
                    if 'Ürün' in df.columns:
                        st.markdown("##### 🏆 En Çok Satan Ürünler")
                        top = df['Ürün'].value_counts().reset_index()
                        top.columns = ['Ürün', 'Adet']
                        st.plotly_chart(px.bar(top, x='Ürün', y='Adet', text='Adet',
                                               color_discrete_sequence=['#2ECC71']), use_container_width=True)
                st.dataframe(df[['Tarih', 'Kutu', 'Ürün', 'Tutar'] if 'Ürün' in df.columns else ['Tarih', 'Kutu', 'Tutar']],
                             use_container_width=True, hide_index=True)
            else:
                st.info("Henüz satış verisi yok.")

        with tab4:
            st.subheader("🌷 Akıllı Dolum Asistanı")
            c1, c2 = st.columns(2)
            with c1:
                sel = st.selectbox("Hangi Rafı Dolduruyorsun?", sorted_ids, key="restock_sel")
                cur = slots[sel]
                st.write(f"**Mevcut:** {cur.get('product_name', '—')} — {cur.get('price', 0)} ₺")
                if cur.get('image_url'): st.image(cur['image_url'], width=160)
                nn = st.text_input("Ürün Adı", value=cur.get('product_name', ''))
                np_val = st.number_input("Satış Fiyatı (₺)", min_value=0, value=int(cur.get('price', 0)))
            with c2:
                st.write("📸 **Ürün Fotoğrafı**")
                use_cam = st.toggle("Kamera Kullan (Mobil)")
                im = st.camera_input("Fotoğraf Çek") if use_cam else st.file_uploader("Dosya Seç", type=['jpg','jpeg','png'])
            st.divider()
            if st.button("🚀 Dolumu Tamamla ve Kaydet", type="primary", use_container_width=True):
                if not nn:
                    st.error("Ürün adı giriniz.")
                else:
                    with st.spinner("Kaydediliyor..."):
                        url = upload_image_to_firebase(im, mid, sel) if im else cur.get('image_url')
                        update_product_info(mid, sel, nn, np_val, url)
                    st.success("Başarıyla güncellendi!"); time.sleep(1); st.rerun()

    if st.session_state.get('is_admin') and menu == "Kullanıcılar":
        st.header("👥 Kullanıcılar")
        users = get_all_users()
        all_machines = get_all_machines()
        for uid, udata in users.items():
            if udata.get("email") == ADMIN_EMAIL: continue
            with st.expander(f"{udata.get('full_name')} ({udata.get('email')})"):
                with st.form(f"u_{uid}"):
                    appr = st.checkbox("Onay", udata.get('approved'))
                    machi = st.multiselect("Makineler", all_machines, default=[m for m in udata.get("machines", []) if m in all_machines])
                    if st.form_submit_button("Kaydet"): update_user_status(uid, appr, machi); st.success("OK"); time.sleep(1); st.rerun()

    if st.session_state.get('is_admin') and menu == "Geri Bildirimler":
        st.header("📬 Mesajlar")
        msgs = get_feedbacks()
        for m in msgs:
            with st.expander(f"{m.get('user_name')} - {m.get('subject')}"):
                st.write(m.get('message'))
                if m.get('status') == 'replied': st.success(f"Yanıt: {m.get('admin_reply')}")
                else:
                    with st.form(f"rep_{m.get('id')}"):
                        rt = st.text_area("Yanıt")
                        if st.form_submit_button("Gönder"): reply_to_feedback(m.get('id'), rt); st.rerun()

    if menu == "Destek & İletişim":
        st.header("Destek")
        t1, t2 = st.tabs(["Yaz", "Geçmiş"])
        with t1:
            with st.form("fb"):
                subj = st.selectbox("Konu", ["Hata", "İstek", "Diğer"]); msg = st.text_area("Mesaj")
                if st.form_submit_button("Gönder"): send_feedback(st.session_state['user_uid'], st.session_state.get('user_email'), st.session_state.get('user_name'), subj, msg); st.success("Gitti!")
        with t2:
            for m in get_feedbacks(st.session_state['user_uid']):
                with st.expander(f"{m.get('subject')}"):
                    st.write(m.get('message'))
                    if m.get('status') == 'replied': 
                        st.success(f"Yanıt: {m.get('admin_reply')}")
                        if st.button("Sil", key=f"del_{m.get('id')}"): delete_feedback(m.get('id')); st.rerun()
                    else: st.warning("Bekliyor")

    if menu == "Hesabım":
        st.header("Hesap"); c1, c2 = st.columns(2)
        c1.info(f"Kullanıcı: {st.session_state.get('user_name')}")
        with c2.form("cpw"):
            op = st.text_input("Eski", type="password"); np = st.text_input("Yeni", type="password")
            if st.form_submit_button("Değiştir"):
                if verify_old_password(st.session_state.get('user_email'), op):
                    change_user_password_admin_sdk(st.session_state['user_uid'], np); st.success("OK")
                else: st.error("Hata")

if 'logged_in' not in st.session_state: st.session_state['logged_in'] = False
if 'show_login' not in st.session_state: st.session_state['show_login'] = False
if st.session_state['logged_in']: dashboard_content()
else: public_website()
