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
    menu_cols = st.columns([1, 4, 1])
    with menu_cols[0]: st.markdown("### 🌸 Açelya")
    with menu_cols[2]:
        if st.button("🔐 Panel Girişi", type="primary", use_container_width=True):
            st.session_state['show_login'] = True; st.rerun()
    if st.session_state.get('show_login'): login_section(); return
    tabs = st.tabs(["🏠 Ana Sayfa", "🚀 Özellikler", "🤖 Modellerimiz", "📞 İletişim"])
    with tabs[0]:
        st.markdown("""<div style="text-align: center; padding: 50px; background-color: #fdf2f8; border-radius: 20px;">
            <h1 style="color: #D9007E;">Çiçekçilikte Yeni Bir Çağ</h1></div>""", unsafe_allow_html=True)

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
