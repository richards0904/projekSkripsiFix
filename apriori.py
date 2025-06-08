import mysql.connector
from mysql.connector import Error
import pandas as pd
from mlxtend.preprocessing import TransactionEncoder
from mlxtend.frequent_patterns import apriori, association_rules
import argparse
import json
from collections import Counter

# --- Konfigurasi Database ---
DB_CONFIG = {
    'host': '127.0.0.1',
    'database': 'jala_seafood', # Sesuaikan
    'user': 'root',
    'password': '' # Sesuaikan
}

def get_db_connection():
    """Membuat koneksi ke database."""
    conn = None
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
    except Error as e:
        print(json.dumps({"error": f"Database connection error: {e}"}), flush=True)
        exit()
    return conn

def fetch_and_group_transactions(conn, tanggal_awal=None, tanggal_akhir=None, min_item_occurrence=1):
    """Mengambil data transaksi, melakukan pruning item, dan mengelompokkannya per order_id."""
    if not conn or not conn.is_connected():
        return []

    cursor = conn.cursor(dictionary=True)
    query = "SELECT order_id, item FROM transaksi"
    params = []
    conditions = []

    if tanggal_awal:
        conditions.append("date >= %s")
        params.append(tanggal_awal)
    if tanggal_akhir:
        conditions.append("date <= %s")
        params.append(tanggal_akhir)

    if conditions:
        query += " WHERE " + " AND ".join(conditions)
    query += " ORDER BY order_id"

    try:
        cursor.execute(query, params if params else None)
        rows = cursor.fetchall()

        if not rows:
            return []

        initial_transactions_dict = {}
        for row in rows:
            order_id = row['order_id']
            item_name = str(row['item']).strip()
            if item_name:
                if order_id not in initial_transactions_dict:
                    initial_transactions_dict[order_id] = []
                initial_transactions_dict[order_id].append(item_name)

        initial_transactions_list = [trx for trx in initial_transactions_dict.values() if trx]

        if not initial_transactions_list:
            return []

        final_transactions_list = initial_transactions_list
        if min_item_occurrence is not None and min_item_occurrence > 1:
            all_items_flat = [item for transaction in initial_transactions_list for item in transaction]
            if not all_items_flat:
                return []

            item_counts = Counter(all_items_flat)
            items_to_keep = {item for item, count in item_counts.items() if count >= min_item_occurrence}

            pruned_list = []
            for transaction in initial_transactions_list:
                pruned_transaction = [item for item in transaction if item in items_to_keep]
                if pruned_transaction:
                    pruned_list.append(pruned_transaction)
            final_transactions_list = pruned_list

            if not final_transactions_list:
                return []

        return final_transactions_list

    except Error as e:
        print(json.dumps({"error": f"Error fetching/processing transactions: {e}"}), flush=True)
        return {"error_detail": f"SQL Error: {str(e)}"}
    finally:
        if cursor:
            cursor.close()

def transform_to_binary_format(transactions_list):
    """Mengubah list of lists transaksi menjadi DataFrame biner (0/1)."""
    if not transactions_list or not isinstance(transactions_list, list) or len(transactions_list) == 0:
        return None

    cleaned_transactions_list = [trx for trx in transactions_list if trx]
    if not cleaned_transactions_list:
        return None

    te = TransactionEncoder()
    try:
        te_ary = te.fit(cleaned_transactions_list).transform(cleaned_transactions_list)
    except ValueError as ve:
         print(json.dumps({"error": f"Error in TransactionEncoder: {ve}. Input sample: {cleaned_transactions_list[:2]}"}), flush=True)
         return None

    df_boolean = pd.DataFrame(te_ary, columns=te.columns_)
    df_biner_integer = df_boolean.astype(int) # Tetap menggunakan integer
    return df_biner_integer

def run_apriori_analysis(df_transaksi_biner, min_support, min_confidence):
    """Menjalankan Apriori dan menghasilkan association rules dengan consequent tunggal dan reduksi redundansi."""
    if df_transaksi_biner is None or df_transaksi_biner.empty:
        return []

    try:
        frequent_itemsets = apriori(df_transaksi_biner, min_support=min_support, use_colnames=True)

        if frequent_itemsets.empty:
            return []

        rules = association_rules(frequent_itemsets, metric="confidence", min_threshold=min_confidence)

        if rules.empty:
            return []

        # Filter agar consequent hanya 1 item
        rules['consequent_len'] = rules['consequents'].apply(lambda x: len(x))
        rules_single_consequent = rules[rules['consequent_len'] == 1].copy()

        if not rules_single_consequent.empty:
            rules_single_consequent.drop(columns=['consequent_len'], inplace=True)
        else: # Jika setelah filter consequent tunggal tidak ada aturan tersisa
            return []


        # --- AWAL MODIFIKASI: Mengurangi Redundansi dengan Memilih Aturan "Perwakilan" ---
        if not rules_single_consequent.empty:
            # 1. Buat kunci unik untuk setiap itemset dasar (gabungan antecedents + consequents, diurutkan)
            #    Ini membantu mengidentifikasi aturan yang berasal dari itemset frequent yang sama.
            rules_single_consequent['itemset_key'] = rules_single_consequent.apply(
                lambda row: tuple(sorted(list(row['antecedents']) + list(row['consequents']))), axis=1
            )

            # 2. Pilih aturan dengan 'confidence' tertinggi untuk setiap 'itemset_key' unik.
            #    Jika ada beberapa aturan dengan confidence sama untuk itemset_key yang sama,
            #    maka pilih yang 'lift'-nya tertinggi. Jika lift juga sama, ambil yang pertama.
            rules_simplified = rules_single_consequent.sort_values(['confidence', 'lift'], ascending=[False, False]) \
                                                      .drop_duplicates('itemset_key', keep='first') \
                                                      .sort_index() # Opsional, mengembalikan ke urutan index asli jika diinginkan

            # Hapus kolom 'itemset_key' jika tidak ingin disertakan di output akhir
            rules_simplified.drop(columns=['itemset_key'], inplace=True)
        else:
            rules_simplified = pd.DataFrame() # DataFrame kosong jika tidak ada aturan setelah filter consequent
        # --- AKHIR MODIFIKASI ---


        if rules_simplified.empty:
            return []

        # Pilih dan format kolom untuk output JSON dari DataFrame yang sudah disederhanakan
        rules_output = rules_simplified[['antecedents', 'consequents', 'support', 'confidence', 'lift']].copy()
        rules_output['antecedents'] = rules_output['antecedents'].apply(lambda x: sorted(list(x)))
        rules_output['consequents'] = rules_output['consequents'].apply(lambda x: sorted(list(x)))

        return rules_output.to_dict(orient='records')
    except MemoryError as me:
        return {"error": f"MemoryError during analysis: {str(me)}. Try increasing min_support or min_item_occurrence."}
    except Exception as e:
        return {"error": f"Error during Apriori analysis: {str(e)}"}


def main():
    parser = argparse.ArgumentParser(description="Run Apriori algorithm on transaction data.")
    parser.add_argument('--min_support', type=float, required=True, help='Minimum support threshold')
    parser.add_argument('--min_confidence', type=float, required=True, help='Minimum confidence threshold')
    parser.add_argument('--tanggal_awal', type=str, help='Start date (YYYY-MM-DD)', required=False, default=None)
    parser.add_argument('--tanggal_akhir', type=str, help='End date (YYYY-MM-DD)', required=False, default=None)
    parser.add_argument('--min_item_occurrence', type=int, help='Minimum times an item must appear to be included. Default is 1 (no pruning by default).', required=False, default=1)

    args = parser.parse_args()

    if args.tanggal_awal and args.tanggal_akhir and args.tanggal_awal > args.tanggal_akhir:
        print(json.dumps({"error": "Tanggal awal tidak boleh lebih besar dari tanggal akhir."}), flush=True)
        exit()

    db_connection = get_db_connection()
    hasil_json_rules = []

    if db_connection:
        list_transaksi_dikelompokkan = fetch_and_group_transactions(
            db_connection,
            args.tanggal_awal,
            args.tanggal_akhir,
            min_item_occurrence=args.min_item_occurrence
        )

        if isinstance(list_transaksi_dikelompokkan, dict) and 'error_detail' in list_transaksi_dikelompokkan:
             if db_connection.is_connected():
                db_connection.close()
             hasil_json_rules = {"error": "Gagal mengambil data transaksi.", "details": list_transaksi_dikelompokkan['error_detail']}
        elif not list_transaksi_dikelompokkan:
            hasil_json_rules = []
        else:
            df_transaksi_biner = transform_to_binary_format(list_transaksi_dikelompokkan)

            if df_transaksi_biner is not None:
                apriori_result = run_apriori_analysis(df_transaksi_biner, args.min_support, args.min_confidence)

                if isinstance(apriori_result, dict) and 'error' in apriori_result:
                    hasil_json_rules = apriori_result
                else:
                    hasil_json_rules = apriori_result
            else:
                hasil_json_rules = []

        if db_connection.is_connected():
            db_connection.close()
    else:
        hasil_json_rules = {"error": "Koneksi database gagal."}

    print(json.dumps(hasil_json_rules, indent=2), flush=True)

if __name__ == '__main__':
    main()
