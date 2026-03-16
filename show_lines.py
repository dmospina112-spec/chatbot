with open('index.php','r',encoding='utf-8') as f:
    for i,line in enumerate(f,1):
        if 430 <= i <= 520:
            print(f'{i:04d}: {line.rstrip()}')
