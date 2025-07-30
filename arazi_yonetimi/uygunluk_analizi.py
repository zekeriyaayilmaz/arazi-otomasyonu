#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Arazi Uygunluk Analizi
Bu script, toprak analiz verilerini kullanarak ekin uygunluğunu hesaplar
"""

import mysql.connector
import pandas as pd
import numpy as np
import folium
from folium import plugins
import json
import sys
import os
from datetime import datetime

class UygunlukAnalizi:
    def __init__(self):
        self.db_config = {
            'host': 'localhost',
            'database': 'arazi_yonetimi',
            'user': 'root',
            'password': ''
        }
        self.conn = None
        
    def veritabani_baglan(self):
        """Veritabanına bağlantı kur"""
        try:
            self.conn = mysql.connector.connect(**self.db_config)
            print("✅ Veritabanı bağlantısı başarılı")
            return True
        except mysql.connector.Error as err:
            print(f"❌ Veritabanı bağlantı hatası: {err}")
            return False
    
    def veri_cek(self):
        """Analiz için gerekli verileri çek"""
        try:
            # Araziler ve son toprak analizleri
            query = """
            SELECT 
                a.id as arazi_id,
                a.arazi_adi,
                a.enlem,
                a.boylam,
                a.alan_m2,
                t.ph_degeri,
                t.nem_orani,
                t.organik_madde,
                t.azot,
                t.fosfor,
                t.potasyum,
                t.analiz_tarihi
            FROM araziler a
            LEFT JOIN (
                SELECT arazi_id, ph_degeri, nem_orani, organik_madde, 
                       azot, fosfor, potasyum, analiz_tarihi,
                       ROW_NUMBER() OVER (PARTITION BY arazi_id ORDER BY analiz_tarihi DESC) as rn
                FROM toprak_analizi
            ) t ON a.id = t.arazi_id AND t.rn = 1
            WHERE t.ph_degeri IS NOT NULL
            """
            
            araziler_df = pd.read_sql(query, self.conn)
            
            # Ekin türleri
            ekin_query = "SELECT * FROM ekin_turleri"
            ekinler_df = pd.read_sql(ekin_query, self.conn)
            
            print(f"📊 {len(araziler_df)} arazi ve {len(ekinler_df)} ekin türü yüklendi")
            return araziler_df, ekinler_df
            
        except Exception as e:
            print(f"❌ Veri çekme hatası: {e}")
            return None, None
    
    def uygunluk_hesapla(self, araziler_df, ekinler_df):
        """Her arazi için ekin uygunluğunu hesapla"""
        uygunluk_verileri = []
        
        for _, arazi in araziler_df.iterrows():
            for _, ekin in ekinler_df.iterrows():
                # pH uygunluğu (0-100 arası skor)
                if ekin['min_ph'] <= arazi['ph_degeri'] <= ekin['max_ph']:
                    ph_skor = 100
                else:
                    ph_fark = min(abs(arazi['ph_degeri'] - ekin['min_ph']), 
                                 abs(arazi['ph_degeri'] - ekin['max_ph']))
                    ph_skor = max(0, 100 - (ph_fark * 30))
                
                # Nem uygunluğu
                if ekin['min_nem'] <= arazi['nem_orani'] <= ekin['max_nem']:
                    nem_skor = 100
                else:
                    nem_fark = min(abs(arazi['nem_orani'] - ekin['min_nem']), 
                                  abs(arazi['nem_orani'] - ekin['max_nem']))
                    nem_skor = max(0, 100 - (nem_fark * 2))
                
                # Besin elementleri uygunluğu
                azot_skor = min(100, (arazi['azot'] / ekin['gerekli_azot']) * 100) if ekin['gerekli_azot'] > 0 else 50
                fosfor_skor = min(100, (arazi['fosfor'] / ekin['gerekli_fosfor']) * 100) if ekin['gerekli_fosfor'] > 0 else 50
                potasyum_skor = min(100, (arazi['potasyum'] / ekin['gerekli_potasyum']) * 100) if ekin['gerekli_potasyum'] > 0 else 50
                
                # Genel uygunluk skoru (ağırlıklı ortalama)
                genel_skor = (
                    ph_skor * 0.25 + 
                    nem_skor * 0.25 + 
                    azot_skor * 0.2 + 
                    fosfor_skor * 0.15 + 
                    potasyum_skor * 0.15
                )
                
                uygunluk_verileri.append({
                    'arazi_id': arazi['arazi_id'],
                    'ekin_id': ekin['id'],
                    'arazi_adi': arazi['arazi_adi'],
                    'ekin_adi': ekin['ekin_adi'],
                    'uygunluk_skoru': round(genel_skor, 2),
                    'ph_skor': round(ph_skor, 2),
                    'nem_skor': round(nem_skor, 2),
                    'azot_skor': round(azot_skor, 2),
                    'fosfor_skor': round(fosfor_skor, 2),
                    'potasyum_skor': round(potasyum_skor, 2),
                    'enlem': arazi['enlem'],
                    'boylam': arazi['boylam']
                })
        
        return pd.DataFrame(uygunluk_verileri)
    
    def veritabanina_kaydet(self, uygunluk_df):
        """Uygunluk sonuçlarını veritabanına kaydet"""
        try:
            cursor = self.conn.cursor()
            
            # Eski kayıtları temizle
            cursor.execute("DELETE FROM uygunluk_haritasi")
            
            # Yeni kayıtları ekle
            for _, row in uygunluk_df.iterrows():
                query = """
                INSERT INTO uygunluk_haritasi (arazi_id, ekin_id, uygunluk_skoru)
                VALUES (%s, %s, %s)
                """
                cursor.execute(query, (row['arazi_id'], row['ekin_id'], row['uygunluk_skoru']))
            
            self.conn.commit()
            print(f"✅ {len(uygunluk_df)} kayıt veritabanına eklendi")
            
        except Exception as e:
            print(f"❌ Veritabanı kaydetme hatası: {e}")
    
    def harita_olustur(self, araziler_df, uygunluk_df):
        """Interaktif harita oluştur"""
        try:
            # Harita merkezi (arazilerin ortalaması)
            ortalama_enlem = araziler_df['enlem'].mean()
            ortalama_boylam = araziler_df['boylam'].mean()
            
            # Folium haritası oluştur
            m = folium.Map(
                location=[ortalama_enlem, ortalama_boylam],
                zoom_start=12,
                tiles='OpenStreetMap'
            )
            
            # Her arazi için en uygun ekini bul
            en_uygun = uygunluk_df.loc[uygunluk_df.groupby('arazi_id')['uygunluk_skoru'].idxmax()]
            
            # Renk skalası (uygunluk skoruna göre)
            def renk_sec(skor):
                if skor >= 80:
                    return 'green'
                elif skor >= 60:
                    return 'orange'
                elif skor >= 40:
                    return 'yellow'
                else:
                    return 'red'
            
            # Arazileri haritaya ekle
            for _, arazi in en_uygun.iterrows():
                popup_text = f"""
                <b>{arazi['arazi_adi']}</b><br>
                En Uygun Ekin: <b>{arazi['ekin_adi']}</b><br>
                Uygunluk Skoru: <b>{arazi['uygunluk_skoru']}%</b><br>
                pH Skoru: {arazi['ph_skor']}%<br>
                Nem Skoru: {arazi['nem_skor']}%<br>
                Azot Skoru: {arazi['azot_skor']}%<br>
                Fosfor Skoru: {arazi['fosfor_skor']}%<br>
                Potasyum Skoru: {arazi['potasyum_skor']}%
                """
                
                folium.CircleMarker(
                    location=[arazi['enlem'], arazi['boylam']],
                    radius=15,
                    popup=popup_text,
                    color='black',
                    weight=2,
                    fillColor=renk_sec(arazi['uygunluk_skoru']),
                    fillOpacity=0.7
                ).add_to(m)
            
            # Lejand ekle
            legend_html = '''
            <div style="position: fixed; 
                        bottom: 50px; left: 50px; width: 200px; height: 120px; 
                        background-color: white; border:2px solid grey; z-index:9999; 
                        font-size:14px; padding: 10px">
            <p><b>Uygunluk Skoru</b></p>
            <p><i class="fa fa-circle" style="color:green"></i> 80-100%: Çok Uygun</p>
            <p><i class="fa fa-circle" style="color:orange"></i> 60-79%: Uygun</p>
            <p><i class="fa fa-circle" style="color:yellow"></i> 40-59%: Orta</p>
            <p><i class="fa fa-circle" style="color:red"></i> 0-39%: Uygun Değil</p>
            </div>
            '''
            m.get_root().html.add_child(folium.Element(legend_html))
            
            # Haritayı kaydet
            m.save('uygunluk_haritasi.html')
            print("✅ Interaktif harita oluşturuldu: uygunluk_haritasi.html")
            
        except Exception as e:
            print(f"❌ Harita oluşturma hatası: {e}")
    
    def rapor_olustur(self, uygunluk_df):
        """Analiz raporu oluştur"""
        try:
            print("\n" + "="*50)
            print("📋 UYGUNLUK ANALİZİ RAPORU")
            print("="*50)
            
            # Genel istatistikler
            print(f"Toplam Arazi Sayısı: {uygunluk_df['arazi_id'].nunique()}")
            print(f"Analiz Edilen Ekin Türü: {uygunluk_df['ekin_id'].nunique()}")
            print(f"Ortalama Uygunluk Skoru: {uygunluk_df['uygunluk_skoru'].mean():.2f}%")
            
            # En uygun ekin-arazi eşleşmeleri
            print("\n🏆 EN UYGUN EKİN-ARAZİ EŞLEŞMELERİ:")
            en_iyiler = uygunluk_df.nlargest(10, 'uygunluk_skoru')
            for _, row in en_iyiler.iterrows():
                print(f"• {row['arazi_adi']} → {row['ekin_adi']} (%{row['uygunluk_skoru']})")
            
            # Ekin türlerine göre ortalama uygunluk
            print("\n🌾 EKİN TÜRLERİNE GÖRE ORTALAMA UYGUNLUK:")
            ekin_ortalama = uygunluk_df.groupby('ekin_adi')['uygunluk_skoru'].mean().sort_values(ascending=False)
            for ekin, skor in ekin_ortalama.items():
                print(f"• {ekin}: %{skor:.2f}")
            
            print("="*50)
            
        except Exception as e:
            print(f"❌ Rapor oluşturma hatası: {e}")

def main():
    """Ana fonksiyon"""
    print("🚀 Arazi Uygunluk Analizi Başlatılıyor...")
    
    analiz = UygunlukAnalizi()
    
    # Veritabanı bağlantısı
    if not analiz.veritabani_baglan():
        sys.exit(1)
    
    try:
        # Verileri çek
        araziler_df, ekinler_df = analiz.veri_cek()
        if araziler_df is None or ekinler_df is None:
            print("❌ Veri çekme başarısız!")
            sys.exit(1)
        
        if len(araziler_df) == 0:
            print("⚠️  Toprak analizi yapılmış arazi bulunamadı!")
            sys.exit(1)
        
        # Uygunluk analizi yap
        print("🔄 Uygunluk hesaplamaları yapılıyor...")
        uygunluk_df = analiz.uygunluk_hesapla(araziler_df, ekinler_df)
        
        # Sonuçları kaydet
        print("💾 Sonuçlar veritabanına kaydediliyor...")
        analiz.veritabanina_kaydet(uygunluk_df)
        
        # Harita oluştur
        print("🗺️  Interaktif harita oluşturuluyor...")
        analiz.harita_olustur(araziler_df, uygunluk_df)
        
        # Rapor oluştur
        analiz.rapor_olustur(uygunluk_df)
        
        print("\n🎉 Analiz başarıyla tamamlandı!")
        
    except Exception as e:
        print(f"❌ Genel hata: {e}")
        sys.exit(1)
    
    finally:
        if analiz.conn:
            analiz.conn.close()

if __name__ == "__main__":
    main()