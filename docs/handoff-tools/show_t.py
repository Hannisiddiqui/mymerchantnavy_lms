import json,sys
d=json.load(open('dumpfull.json',encoding='utf-8'))
t=sys.argv[1]; lo=int(sys.argv[2]) if len(sys.argv)>2 else 0; hi=int(sys.argv[3]) if len(sys.argv)>3 else 100
print('##',t,d[t]['title'])
for q in d[t]['q'][lo:hi]:
    print(f"{q['n']}. {q['b']} | "+" ; ".join(f"{'ABCD'[k]}){o}" for k,o in enumerate(q['o']))+f" || {'ABCD'[q['c']-1]}")
