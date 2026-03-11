import random
import json
import urllib.request

# Collection of sahih hadith about trade/commerce (perdagangan/jual beli)
hadiths = [
    {
        "text": "Rasulullah ﷺ bersabda:\n\n_\"Pedagang yang jujur dan terpercaya akan bersama para nabi, orang-orang shiddiq, dan para syuhada (pada hari kiamat).\"_\n\n📖 *HR. Tirmidzi no. 1209* — Hasan Shahih",
        "theme": "Kejujuran pedagang"
    },
    {
        "text": "Rasulullah ﷺ bersabda:\n\n_\"Dua orang yang melakukan jual beli masing-masing memiliki hak khiyar (memilih) selama keduanya belum berpisah. Jika keduanya jujur dan menjelaskan (kondisi barang), maka jual beli mereka diberkahi. Namun jika keduanya menyembunyikan (cacat) dan berdusta, maka keberkahan jual beli mereka dihapus.\"_\n\n📖 *HR. Bukhari no. 2079 & Muslim no. 1532*",
        "theme": "Keberkahan jual beli"
    },
    {
        "text": "Rasulullah ﷺ bersabda:\n\n_\"Sesungguhnya para pedagang akan dibangkitkan pada hari kiamat sebagai orang-orang fajir (jahat), kecuali orang yang bertakwa kepada Allah, berbuat baik, dan berkata jujur.\"_\n\n📖 *HR. Tirmidzi no. 1210, Ibnu Majah no. 2146* — Hasan Shahih",
        "theme": "Pedagang dan hari kiamat"
    },
    {
        "text": "Rasulullah ﷺ bersabda:\n\n_\"Penjual dan pembeli memiliki hak khiyar (memilih) selama keduanya belum berpisah dari tempat jual beli.\"_\n\n📖 *HR. Bukhari no. 2107 & Muslim no. 1531*",
        "theme": "Hak khiyar dalam jual beli"
    },
    {
        "text": "Rasulullah ﷺ bersabda:\n\n_\"Allah merahmati seseorang yang bersikap mudah (toleran) ketika menjual, mudah ketika membeli, dan mudah ketika menagih.\"_\n\n📖 *HR. Bukhari no. 2076*",
        "theme": "Toleransi dalam berdagang"
    },
    {
        "text": "Rasulullah ﷺ bersabda:\n\n_\"Tidaklah seorang muslim menanam tanaman atau bercocok tanam lalu dimakan darinya oleh burung, manusia, atau binatang, melainkan menjadi sedekah baginya.\"_\n\n📖 *HR. Bukhari no. 2320 & Muslim no. 1553*",
        "theme": "Usaha dan sedekah"
    },
    {
        "text": "Rasulullah ﷺ bersabda:\n\n_\"Tidak halal bagi seseorang menjual suatu barang yang dia tahu ada cacatnya, kecuali dia menjelaskan cacat tersebut.\"_\n\n📖 *HR. Ahmad no. 15978, Ibnu Majah no. 2246* — Shahih",
        "theme": "Larangan menyembunyikan cacat barang"
    },
    {
        "text": "Rasulullah ﷺ bersabda:\n\n_\"Sebaik-baik penghasilan adalah penghasilan seorang pekerja, apabila dia mengerjakannya dengan ikhlas.\"_\n\n📖 *HR. Ahmad no. 8419* — Hasan",
        "theme": "Usaha dengan ikhlas"
    },
    {
        "text": "Rasulullah ﷺ bersabda:\n\n_\"Barangsiapa yang menangguhkan (hutang) orang yang kesulitan, atau membebaskannya, maka Allah akan menaunginya di bawah naungan-Nya pada hari yang tidak ada naungan kecuali naungan-Nya.\"_\n\n📖 *HR. Muslim no. 3006*",
        "theme": "Memberi kelonggaran hutang"
    },
    {
        "text": "Rasulullah ﷺ bersabda:\n\n_\"Rasulullah ﷺ melarang jual beli gharar (yang mengandung ketidakjelasan).\"_\n\n📖 *HR. Muslim no. 1513*",
        "theme": "Larangan jual beli gharar"
    },
]

hadith = random.choice(hadiths)

header = "🕌 *Hadits Harian — Adab Jual Beli*\n\n"
footer = "\n\n─────────────────\n_🤖 Bot Marketplace Jamaah — Muamalah yang berkah dimulai dari adab yang baik_"

message = header + hadith["text"] + footer

data = {
    "phone_id": "6281317647379",
    "group": "Marketplace Jamaah",
    "message": message
}

payload = json.dumps(data).encode("utf-8")
req = urllib.request.Request(
    "http://localhost:3001/api/sendGroup",
    data=payload,
    headers={
        "Content-Type": "application/json",
        "Authorization": "Bearer fc42fe461f106cdee387e807b972b52b"
    },
    method="POST"
)

try:
    with urllib.request.urlopen(req, timeout=30) as resp:
        print(f"Status: {resp.status}")
        body = resp.read().decode()
        print(body)
        print(f"\nHadith sent: {hadith['theme']}")
except Exception as e:
    print(f"Error: {e}")
    if hasattr(e, 'read'):
        print(e.read().decode())
