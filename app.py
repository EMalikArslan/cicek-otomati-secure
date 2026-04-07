import streamlit as st
import firebase_admin
from firebase_admin import credentials, db, storage
import time
from datetime import datetime
import pandas as pd
import plotly.express as px
from PIL import Image as PILImage, ImageOps
import io
import requests

# ====================================================================
# 1. AYARLAR VE BAĞLANTI
# ====================================================================
st.set_page_config(
    page_title="ETM 7/24 Panel",
    page_icon="🌸",
    layout="wide",
    initial_sidebar_state="expanded"
)

# --- SÜPER ADMIN MAIL ADRESİ (BURAYI KENDİ MAİLİNLE DEĞİŞTİR) ---
ADMIN_EMAIL = "ensmlk.68@gmail.com"  # <--- BURAYA DİKKAT!

# --- WEB API KEY ---
FIREBASE_WEB_API_KEY = "AIzaSyBO_h7J_fN3XiMLhIjlNge6M02rJ-zlkZM"

# --- BUCKET ADRESİ ---
STORAGE_BUCKET_NAME = 'flowervending-3b118.firebasestorage.app'

# Firebase'i başlat
if not firebase_admin._apps:
    try:
        cred = credentials.Certificate("serviceAccountKey.json")
        firebase_admin.initialize_app(cred, {
            'databaseURL': "https://flowervending-3b118-default-rtdb.europe-west1.firebasedatabase.app/",
            'storageBucket': STORAGE_BUCKET_NAME
        })
    except Exception as e:
        st.error(f"Firebase Bağlantı Hatası: {e}")
        st.stop()

# ====================================================================
# 2. YARDIMCI FONKSİYONLAR (AUTH & ADMIN)
# ====================================================================
def auth_request(endpoint, email, password):
    try:
        url = f"https://identitytoolkit.googleapis.com/v1/accounts:{endpoint}?key={FIREBASE_WEB_API_KEY}"
        headers = {"Content-Type": "application/json"}
        data = {"email": email, "password": password, "returnSecureToken": True}
        response = requests.post(url, headers=headers, json=data)
        response.raise_for_status()
        return response.json()
    except: return None

def create_user_db_entry(uid, email, full_name):
    try:
        db.reference(f'users/{uid}').set({
            "email": email, "full_name": full_name, "approved": False, "machines": ["DEMO"]
        })
        return True
    except: return False

def get_user_data(uid):
    try: return db.reference(f'users/{uid}').get()
    except: return None

# --- YENİ: ADMIN FONKSİYONLARI ---
def get_all_users():
    """Tüm kayıtlı kullanıcıları çeker"""
    try: return db.reference('users').get()
    except: return {}

def get_all_machines():
    """Sistemdeki tüm makinelerin listesini çeker"""
    try:
        data = db.reference('machines').get()
        return list(data.keys()) if data else []
    except: return []

def update_user_status(user_id, approved, machines_list):
    """Kullanıcıyı onaylar ve makine atar"""
    try:
        db.reference(f'users/{user_id}').update({
            "approved": approved,
            "machines": machines_list
        })
        return True
    except: return False

# ====================================================================
# 3. YARDIMCI FONKSİYONLAR (SİSTEM)
# ====================================================================
def get_machine_status(machine_id):
    try: return db.reference(f'machines/{machine_id}/info').get()
    except: return None

def get_slots(machine_id):
    try:
        data = db.reference(f'machines/{machine_id}/slots').get()
        if isinstance(data, list):
            new_dict = {}
            for i, item in enumerate(data):
                if item: new_dict[str(i)] = item
            return new_dict
        return data if data else {}
    except: return {}

def get_sales_history(machine_id):
    try:
        data = db.reference(f'machines/{machine_id}/satis_hareketleri').get()
        if not data: return None
        sales_list = []
        for key, val in data.items(): sales_list.append(val)
        df = pd.DataFrame(sales_list)
        if 'tarih' in df.columns: df['tarih'] = pd.to_datetime(df['tarih'])
        kutu_col = 'kutu' if 'kutu' in df.columns else 'kutu_no'
        if kutu_col in df.columns:
            df = df.rename(columns={'tarih': 'Tarih', kutu_col: 'Kutu', 'fiyat': 'Tutar', 'durum': 'Durum'})
            df = df.sort_values(by='Tarih', ascending=False)
        return df
    except: return None

def send_open_command(machine_id, slot_id):
    try:
        ref = db.reference(f'machines/{machine_id}/commands')
        ref.update({"open_gate": str(slot_id), "timestamp": time.time()})
        st.toast(f"🚪 {slot_id}. Kapak İçin Sinyal Gönderildi!", icon="✅")
    except Exception as e: st.error(f"Komut Hatası: {e}")
    
def update_slot(machine_id, slot_id, price, enabled):
    ref = db.reference(f'machines/{machine_id}/slots/{slot_id}')
    ref.update({"price": price, "enabled": enabled})

def upload_image_to_firebase(image_file, machine_id, slot_id):
    try:
        image = PILImage.open(image_file)
        image = ImageOps.exif_transpose(image)
        if image.mode in ("RGBA", "P"): image = image.convert("RGB")
        image.thumbnail((500, 500)) 
        img_byte_arr = io.BytesIO()
        image.save(img_byte_arr, format='JPEG', quality=70, optimize=True)
        img_byte_arr = img_byte_arr.getvalue()
        bucket = storage.bucket()
        blob = bucket.blob(f"machines/{machine_id}/current_slot_{slot_id}.jpg")
        blob.cache_control = 'public, max-age=0'
        blob.upload_from_string(img_byte_arr, content_type='image/jpeg')
        blob.make_public()
        return f"{blob.public_url}?v={int(time.time())}"
    except Exception as e:
        st.error(f"Resim yükleme hatası: {e}"); return None

def update_product_info(machine_id, slot_id, name, price, img_url):
    ref = db.reference(f'machines/{machine_id}/slots/{slot_id}')
    update_data = {"price": price, "product_name": name, "enabled": True, "last_restock": datetime.now().strftime("%Y-%m-%d %H:%M:%S")}
    if img_url: update_data["image_url"] = img_url
    ref.update(update_data)

# ====================================================================
# 4. GİRİŞ VE KAYIT
# ====================================================================
def login_page():
    st.markdown("<h1 style='text-align: center; color: #D9007E;'>ETM 7/24 Bayi Paneli</h1>", unsafe_allow_html=True)
    col1, col2, col3 = st.columns([1,2,1])
    
    with col2:
        tab_login, tab_register = st.tabs(["Giriş Yap", "Yeni Kayıt Oluştur"])
        
        with tab_login:
            with st.form("login_form"):
                email = st.text_input("E-Posta")
                password = st.text_input("Şifre", type="password")
                if st.form_submit_button("Giriş Yap", type="primary"):
                    if not email or not password:
                        st.warning("Eksik bilgi.")
                    else:
                        with st.spinner("Giriş yapılıyor..."):
                            res = auth_request("signInWithPassword", email, password)
                            if res and "localId" in res:
                                uid = res["localId"]
                                
                                # --- ADMIN KONTROLÜ ---
                                if email == ADMIN_EMAIL:
                                    st.success("👑 Hoşgeldin Süper Admin!")
                                    st.session_state['logged_in'] = True
                                    st.session_state['user'] = "Yönetici"
                                    st.session_state['is_admin'] = True # Admin bayrağı
                                    # Admin tüm makineleri görür
                                    all_machines = get_all_machines()
                                    st.session_state['machines'] = all_machines if all_machines else ["ETM_001"]
                                    time.sleep(0.5)
                                    st.rerun()
                                # ----------------------
                                
                                user_data = get_user_data(uid)
                                if user_data and user_data.get("approved") == True:
                                    st.success("Giriş Başarılı!")
                                    st.session_state['logged_in'] = True
                                    st.session_state['user'] = user_data.get("full_name", email)
                                    st.session_state['is_admin'] = False
                                    st.session_state['machines'] = user_data.get("machines", [])
                                    time.sleep(0.5)
                                    st.rerun()
                                elif user_data: st.warning("⚠️ Hesabınız henüz onaylanmadı.")
                                else: st.error("Kullanıcı verisi yok.")
                            else: st.error("Hatalı bilgiler.")

        with tab_register:
            with st.form("register_form"):
                n_name = st.text_input("Ad Soyad / Firma")
                n_email = st.text_input("E-Posta")
                n_pass = st.text_input("Şifre", type="password")
                if st.form_submit_button("Kayıt Ol"):
                    res = auth_request("signUp", n_email, n_pass)
                    if res and "localId" in res:
                        create_user_db_entry(res["localId"], n_email, n_name)
                        st.success("Kayıt alındı. Onay bekleniyor.")
                    else: st.error("Kayıt başarısız.")

# ====================================================================
# 5. ADMIN KULLANICI YÖNETİMİ PANELİ (YENİ)
# ====================================================================
def admin_management_panel():
    st.markdown("### 🛡️ Yönetici Paneli - Kullanıcı & Makine Yönetimi")
    
    users = get_all_users()
    all_system_machines = get_all_machines()
    
    if not users:
        st.info("Kayıtlı kullanıcı yok.")
        return

    # Kullanıcıları Listele
    for uid, udata in users.items():
        # Sadece admin olmayanları gösterelim (kendini yönetmesin)
        if udata.get("email") == ADMIN_EMAIL: continue
            
        with st.expander(f"👤 {udata.get('full_name')} ({udata.get('email')}) - {'✅ Onaylı' if udata.get('approved') else '⏳ Bekliyor'}"):
            c1, c2 = st.columns([2, 1])
            
            with c1:
                # Makine Atama (Multi-Select)
                current_machines = udata.get("machines", [])
                if isinstance(current_machines, str): current_machines = [current_machines] # Hata önleyici
                
                selected_machines = st.multiselect(
                    f"{udata.get('full_name')} İçin Yetkili Makineler:",
                    options=all_system_machines, # Tüm makine havuzu
                    default=[m for m in current_machines if m in all_system_machines],
                    key=f"machines_{uid}"
                )
            
            with c2:
                # Onay Durumu
                is_approved = st.checkbox("Hesabı Onayla / Aktif Et", value=udata.get("approved", False), key=f"app_{uid}")
                
                if st.button("💾 Kaydet", key=f"save_{uid}", type="primary"):
                    if update_user_status(uid, is_approved, selected_machines):
                        st.success("Kullanıcı güncellendi!")
                        time.sleep(1)
                        st.rerun()
                    else:
                        st.error("Güncelleme hatası.")

# ====================================================================
# 6. DASHBOARD
# ====================================================================
def dashboard_page():
    c1, c2, c3 = st.columns([6, 2, 1])
    with c1: 
        role = " (Yönetici)" if st.session_state.get('is_admin') else ""
        st.title(f"👋 Hoşgeldin, {st.session_state.get('user')}{role}")
    with c2: 
        if st.button("🔄 Yenile", use_container_width=True): st.rerun()
    with c3:
        if st.button("Çıkış", use_container_width=True):
            st.session_state['logged_in'] = False
            st.rerun()
    st.divider()
    
    # --- EĞER ADMIN İSE YÖNETİM PANELİNİ GÖSTER ---
    if st.session_state.get('is_admin'):
        admin_management_panel()
        st.divider()
        st.markdown("### 🖥️ Tüm Makinelerin Durumu")
    
    machines = st.session_state.get('machines', [])
    if not machines: st.info("Görüntülenecek makine yok."); return

    for mid in machines:
        info = get_machine_status(mid)
        with st.container():
            c1, c2, c3, c4 = st.columns([2, 2, 2, 2])
            is_online = False; temp = 0; location = "---"
            if info:
                last_seen_str = info.get('last_seen', '---')
                temp = info.get('temperature', 0)
                location = info.get('location', '---')
                db_online_status = info.get('online_status', False)
                try:
                    last_time = datetime.strptime(last_seen_str, "%Y-%m-%d %H:%M:%S")
                    if db_online_status and (datetime.now() - last_time).total_seconds() < 300: is_online = True
                except: pass

            with c1:
                st.subheader(f"🖥️ {mid}")
                st.caption(f"Konum: {location}")
            with c2: st.metric("Sıcaklık", f"{temp} °C")
            with c3:
                if is_online: st.metric("Durum", "🟢 Çevrimiçi")
                else: st.metric("Durum", "🔴 Çevrimdışı")
            with c4:
                st.write("")
                if st.button("⚙️ YÖNET", key=f"btn_{mid}", type="primary", use_container_width=True):
                    st.session_state['selected_machine'] = mid
                    st.rerun()
        st.divider()

# ====================================================================
# 7. YÖNETİM SAYFASI (AYNI KALDI)
# ====================================================================
def manage_machine_page():
    mid = st.session_state['selected_machine']
    c_back, c_refresh = st.columns([1, 6])
    with c_back:
        if st.button("⬅️ Geri"): del st.session_state['selected_machine']; st.rerun()
    with c_refresh:
        if st.button("🔄 Yenile", key="refresh_manage"): st.rerun()

    st.header(f"🔧 Ayarlar: {mid}")
    slots = get_slots(mid)
    if not slots: st.warning("Veri yok veya cihaz çevrimdışı."); return

    tab1, tab2, tab3, tab4 = st.tabs(["💰 Fiyat & Stok", "🎮 Uzaktan Kontrol", "📊 Satış & Analiz", "🌷 Akıllı Dolum"])
    
    with tab1:
        st.info("💡 Ürün fotoğrafına tıklayarak büyütebilirsiniz.")
        sorted_ids = sorted(slots.keys(), key=lambda x: int(x) if x.isdigit() else 999)
        with st.form("slots_form"):
            sub_top = st.form_submit_button("💾 TÜMÜNÜ KAYDET", type="primary", use_container_width=True, key="save_top")
            st.divider()
            cols = st.columns(2) 
            for i, sid in enumerate(sorted_ids):
                data = slots[sid]
                with cols[i % 2]:
                    with st.container(border=True):
                        c_img, c_info = st.columns([1, 2.5]) 
                        with c_img:
                            if 'image_url' in data: st.image(data['image_url'], width=120)
                            else: st.write("📷 *Yok*")
                        with c_info:
                            st.markdown(f"##### 📦 Raf {sid}")
                            st.markdown(f"**{data.get('product_name', '---')}**")
                            sub_c1, sub_c2 = st.columns(2)
                            with sub_c1: new_price = st.number_input(f"Fiyat", min_value=0, value=int(data.get('price', 0)), key=f"p_{sid}", label_visibility="collapsed")
                            with sub_c2: is_active = st.checkbox("Satışta", value=data.get('enabled', False), key=f"e_{sid}")
            st.write("")
            sub_btm = st.form_submit_button("💾 TÜMÜNÜ KAYDET", type="primary", use_container_width=True, key="save_bottom")
            if sub_top or sub_btm:
                bar = st.progress(0, text="Kaydediliyor..."); total = len(sorted_ids)
                for i, sid in enumerate(sorted_ids):
                    update_slot(mid, sid, st.session_state[f"p_{sid}"], st.session_state[f"e_{sid}"])
                    bar.progress((i+1)/total)
                st.success("Kaydedildi."); time.sleep(1); st.rerun()

    with tab2:
        st.warning("⚠️ Butonlar fiziksel kapağı anında açar!")
        cols_remote = st.columns(4)
        for i, sid in enumerate(sorted_ids):
            with cols_remote[i % 4]:
                if st.button(f"🔓 AÇ: Raf {sid}", key=f"open_{sid}", use_container_width=True): send_open_command(mid, sid)

    with tab3:
        st.subheader("📊 Satış Analizi")
        df = get_sales_history(mid)
        if df is not None and not df.empty:
            def get_prod(kid): return slots.get(str(kid), {}).get('product_name', f"Kutu {kid}")
            if 'urun' not in df.columns: df['Ürün'] = df['Kutu'].apply(get_prod)
            else: df['Ürün'] = df['urun']
            now = datetime.now()
            today = df[df['Tarih'] >= now.replace(hour=0, minute=0, second=0)]
            month = df[df['Tarih'] >= now.replace(day=1, hour=0, minute=0)]
            c1, c2, c3, c4 = st.columns(4)
            c1.metric("Bugün Ciro", f"{today['Tutar'].sum()} ₺", f"{len(today)} Adet")
            c2.metric("Bu Ay Ciro", f"{month['Tutar'].sum()} ₺", f"{len(month)} Adet")
            c3.metric("Toplam Ciro", f"{df['Tutar'].sum()} ₺", f"{len(df)} Adet")
            c4.metric("Ortalama Sepet", f"{df['Tutar'].mean():.1f} ₺")
            st.divider()
            ch1, ch2 = st.columns(2)
            with ch1:
                st.markdown("##### 🕒 Saatlik Yoğunluk")
                df['Saat'] = df['Tarih'].dt.hour
                full_hours = pd.DataFrame({'Saat': range(24)})
                hourly = df.groupby('Saat').size().reset_index(name='Adet')
                final_h = pd.merge(full_hours, hourly, on='Saat', how='left').fillna(0)
                st.plotly_chart(px.bar(final_h, x='Saat', y='Adet', text='Adet', color_discrete_sequence=['#D9007E']), use_container_width=True)
            with ch2:
                st.markdown("##### 🏆 En Çok Satan Ürünler")
                top = df['Ürün'].value_counts().reset_index()
                top.columns = ['Ürün', 'Adet']
                fig_top = px.bar(top, x='Ürün', y='Adet', text='Adet', color_discrete_sequence=['#2ECC71'])
                fig_top.update_layout(xaxis_title="Ürün Adı", yaxis_title="Satış Adedi")
                st.plotly_chart(fig_top, use_container_width=True)
            st.dataframe(df[['Tarih', 'Kutu', 'Ürün', 'Tutar', 'Durum']], use_container_width=True, hide_index=True)
        else: st.info("Henüz satış verisi yok.")

    with tab4:
        st.subheader("🌷 Akıllı Dolum Asistanı")
        c_left, c_right = st.columns([1, 1])
        with c_left:
            sel_slot = st.selectbox("Hangi Kutuyu Dolduruyorsun?", sorted_ids, key="restock_select")
            curr = slots[sel_slot]
            st.write(f"**Durum:** {curr.get('price')} TL - {'Aktif' if curr.get('enabled') else 'Pasif'}")
            n_name = st.text_input("Çiçek Adı", value=curr.get('product_name', ''))
            n_price = st.number_input("Satış Fiyatı", value=int(curr.get('price', 0)), key="n_price")
        with c_right:
            st.write("📸 **Ürün Fotoğrafı**")
            method = st.radio("Yükleme", ["Kamera (Mobil)", "Dosya (PC)"], horizontal=True)
            img_file = None
            if method == "Kamera (Mobil)":
                if st.checkbox("Kamerayı Başlat 📷"): img_file = st.camera_input("Çek")
            else: img_file = st.file_uploader("Seç", type=['jpg', 'png', 'jpeg'])
        st.divider()
        if st.button("🚀 DOLUMU TAMAMLA VE KAYDET", type="primary", use_container_width=True):
            if not n_name: st.error("Çiçek adı giriniz.")
            else:
                with st.spinner("Yükleniyor..."):
                    url = upload_image_to_firebase(img_file, mid, sel_slot) if img_file else curr.get('image_url')
                    update_product_info(mid, sel_slot, n_name, n_price, url)
                st.success("Başarılı!"); time.sleep(1); st.rerun()

# ====================================================================
# 8. UYGULAMA AKIŞI
# ====================================================================
if 'logged_in' not in st.session_state: st.session_state['logged_in'] = False
if not st.session_state['logged_in']: login_page()
else:
    if 'selected_machine' in st.session_state: manage_machine_page()
    else: dashboard_page()
