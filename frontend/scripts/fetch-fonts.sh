#!/usr/bin/env bash
# بيحمّل خط Cairo وأيقونات Material Symbols (اللي بنستخدمها بس) ويحطهم في public/fonts
# عشان التطبيق يشتغل أوفلاين. لو استخدمت أيقونة جديدة: ضيفها في ICONS وشغّل:  bash scripts/fetch-fonts.sh
set -euo pipefail
cd "$(dirname "$0")/.."

ICONS="arrow_downward arrow_upward bookmark calendar_month checklist_rtl groups_3 restart_alt account_balance account_balance_wallet add add_card arrow_back assignment_late attach_file badge bar_chart block bolt build calculate campaign cancel category chair chat_bubble_outline check check_circle chevron_left chevron_right cleaning_services close construction credit_card dashboard delete description donut_large download edit edit_note engineering error event event_available event_repeat expand_more favorite filter_list gavel groups help history hourglass_top inbox label lightbulb local_shipping lock lock_clock login logout manage_accounts menu_book monitoring more_horiz mosque north_east notifications open_in_new pause_circle payments person person_add photo_camera picture_as_pdf preview print progress_activity receipt receipt_long redo request_quote save schedule school send settings share shield shopping_cart shuffle smartphone south_west storefront swap_horiz table_view task_alt timeline touch_app tune undo verified verified_user visibility visibility_off volunteer_activism warning water_drop widgets wifi zoom_in"

UA="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36"
NAMES=$(echo $ICONS | tr ' ' '\n' | sort -u | paste -sd, -) # لازم أبجدي
ICON_URL="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block&icon_names=$NAMES"
CAIRO_URL="https://fonts.googleapis.com/css2?family=Cairo:wght@400..800&display=swap"

rm -rf public/fonts && mkdir -p public/fonts
OUT=src/fonts.css
echo "/* خطوط مستضافة محليًا عشان تشتغل أوفلاين (PWA/Capacitor). متتعدلش يدوي — اتولّدت بـ scripts/fetch-fonts.sh */" > $OUT

i=0
for U in "$CAIRO_URL" "$ICON_URL"; do
  CSS=$(curl -sf -A "$UA" "$U")
  for F in $(echo "$CSS" | grep -oE "https://fonts.gstatic.com/[^)]+"); do
    i=$((i+1))
    curl -sf -o "public/fonts/f$i.woff2" "$F"
    CSS="${CSS//$F//fonts/f$i.woff2}"
  done
  echo "$CSS" >> $OUT
done

echo "تم: $i ملفات خطوط في public/fonts"
