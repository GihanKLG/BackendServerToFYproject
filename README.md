# BackendServerToFYproject
backend server of the final year project
Convert process of the SLD95 location data into LatLng

Step 1 :- 
Convert SLD95 location data into SLD99

East(SLD99)  =  East(SLD95) + 300,000
North(SLD99)  = North(SLD95) + 300,000

Step2 :- 
Find change degree per one meter

SLD99 => (0, 0) => (2.4711887°, 76.2822415°)
SLD99 => (100000, 100000) => (3.376583°, 77.1763245°)

Change along y axis = 0.0000090539
Change along x axis = 0.0000089410

Step 3 :-
Convert SLD99 location data into LatLng

Lat = North(SLD99) * 0.0000090539 + 2.4711978
Lng = East(SLD99) * 0.0000089410 + 76.2822415


https://epsg.io/transform#s_srs=5235&t_srs=4326&x=500000.0000000&y=500000.0000000

